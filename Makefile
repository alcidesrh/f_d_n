# Parameters
SHELL         = sh

# Executables
DOCKER        = docker
DOCKER_COMP   = docker compose
CONSOLE       = $(DOCKER_COMP) exec backend bin/console

# —— Backend Symfony (inside container) ——————————————————————————————————————
cc: ## Clear the cache (inside container)
	@$(CONSOLE) cache:clear

migration: ## Create a new migration
	@$(CONSOLE) make:migration

migrate: ## Run pending migrations
	@$(CONSOLE) doctrine:migrations:migrate -n

entity-setup: ## Sync dynamic entity config metadata
	@$(CONSOLE) app:config:sync-metadata

.PHONY: migrar-todo
migrar-todo:
	$(CONSOLE) $(if $(prod),--env=prod) app:migrar:todo $(if $(clean),--clean) $(if $(conservar),--conservar) $(if $(estaticos),--estaticos) $(if $(iam),--iam) $(if $(config),--config) $(if $(data),--data) $(if $(boletos),boletos=$(boletos)) $(foreach u,$(entities),--entities=$(u))

sincronizar: ## Sincronización con el legado (ADR-042): un ciclo a mano (el programador la corre cada minuto)
	@$(CONSOLE) app:sincronizar

tareas: ## Programador de tareas (ADR-044): estado de cada tarea; `make tareas t=reportes_diarios` la pone en cola ya
	@$(CONSOLE) app:programador:tareas $(if $(t),--ejecutar=$(t))

test: ## Run all tests
	@$(DOCKER_COMP) exec backend vendor/bin/phpunit -c phpunit.dist.xml

testf: ## Run tests by name filter (usage: make testf F="testMethodName")
	@$(DOCKER_COMP) exec backend vendor/bin/phpunit -c phpunit.dist.xml --filter "$(F)"

# Cuenta de pruebas (Playwright, agentes, curl): credenciales en .env.e2e.local (fuera de git).
E2E_ENV      = .env.e2e.local
E2E_BASE_URL ?= https://localhost

e2e-usuario: ## Crea o reinicia la cuenta de pruebas y guarda sus credenciales en .env.e2e.local (solo desarrollo)
	@umask 077 && $(DOCKER_COMP) exec -T backend bin/console app:usuario:pruebas --no-ansi > $(E2E_ENV).tmp \
		&& { echo "E2E_BASE_URL=$(E2E_BASE_URL)"; cat $(E2E_ENV).tmp; } > $(E2E_ENV) && rm -f $(E2E_ENV).tmp \
		&& echo "Credenciales de la cuenta de pruebas en $(E2E_ENV)" \
		|| { rm -f $(E2E_ENV).tmp; exit 1; }

e2e-token: ## Imprime un token Bearer de la cuenta de pruebas (uso: curl -H "Authorization: Bearer $$(make -s e2e-token)" ...)
	@test -f $(E2E_ENV) || { echo "Falta $(E2E_ENV): make e2e-usuario" >&2; exit 1; }
	@. ./$(E2E_ENV) && curl -skf -X POST "$$E2E_BASE_URL/api/login" -H 'Content-Type: application/json' \
		-d "{\"username\":\"$$E2E_ADMIN_USER\",\"password\":\"$$E2E_ADMIN_PASSWORD\"}" \
		| grep -o '"token":"[^"]*"' | head -n 1 | cut -d '"' -f 4

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
up: ## Start the docker hub (PHP,caddy,MySQL,redis,adminer,elasticsearch)
	$(DOCKER_COMP) up --detach

down: ## Stop the docker hub
	$(DOCKER_COMP) down --remove-orphans

sh: ## Log to the docker container
	@$(DOCKER_COMP) exec backend sh

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

bash: ## Connect to the application container
	@$(DOCKER) exec -it backend bash

b: ## Builds the images (php + caddy)
	$(DOCKER_COMP) build --pull --no-cache

d: ## Stop the docker hub
	$(DOCKER_COMP) down --remove-orphans

debug:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up -d
dev-build:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure APP_ENV=dev $(DOCKER_COMP) up --build  -d
dev:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure APP_ENV=dev $(DOCKER_COMP) up -d

# Producción en la VPS con dominio propio: Caddy saca el certificado de Let's Encrypt para SERVER_NAME.
# Secretos y variables en .env.prod.local (fuera de git; plantilla en .env.prod.dist).
PROD_COMP = $(DOCKER_COMP) --env-file .env.prod.local -f compose.yaml -f compose.prod.yaml

prod-env:
	@test -f .env.prod.local || { echo "Falta .env.prod.local (copia .env.prod.dist y llénalo)" >&2; exit 1; }

prod: prod-env ## Levanta el stack de producción (dominio y secretos en .env.prod.local)
	$(PROD_COMP) up -d

prod-build: prod-env ## Construye las imágenes de producción y levanta el stack (el código va montado: ver `make deploy`)
	$(PROD_COMP) up -d --build

# El código va montado desde este checkout (compose.prod.yaml), no dentro de la imagen:
# un cambio de PHP es un reinicio y uno del frontend, un build en el host.
.PHONY: deploy reiniciar front pagina vendor imagen migraciones
PROD_PHP = backend worker worker-pesadas programador

deploy: prod-env ## Actualiza producción: git pull y, según lo que cambió, vendor, migraciones, front, página, imagen y reinicio de PHP (`desde=<commit>` retoma uno detenido)
	@antes=$(or $(desde),$$(git rev-parse HEAD)); git pull --ff-only; \
	cambio() { git diff --quiet "$$antes" HEAD -- "$$@" || return 0; return 1; }; \
	if cambio backend/composer.lock; then $(MAKE) -s vendor; fi; \
	if cambio backend/migrations; then $(MAKE) -s migraciones desde=$$antes || { echo "Deploy detenido antes de compilar y reiniciar. Resuelto, retoma con: make deploy desde=$$antes" >&2; exit 1; }; fi; \
	if cambio frontend; then $(MAKE) -s front; fi; \
	if cambio pagina frontend/src; then $(MAKE) -s pagina; fi; \
	if cambio backend/Dockerfile frontend/Dockerfile; then $(MAKE) -s imagen; else $(MAKE) -s reiniciar; fi

# Corren antes de reiniciar PHP y solo si todas las pendientes llegaron después de `desde`: una base
# rehecha con SchemaTool (app:reset-db --hard) no tiene las versiones anotadas y migrate intentaría
# todas desde la primera. --all-or-nothing: en PostgreSQL, si una falla no queda ninguna aplicada.
migraciones: prod-env ## Producción: aplica las migraciones pendientes que llegaron después de `desde=<commit>` (lo llama `make deploy`)
	@test -n "$(desde)" || { echo "Uso: make migraciones desde=<commit>" >&2; exit 1; }
	@nuevas=" $$(git diff --name-only --diff-filter=A $(desde) HEAD -- backend/migrations | sed -n 's/.*\(Version[0-9]\{14\}\)\.php$$/\1/p' | tr '\n' ' ')"; \
	estado=$$($(PROD_COMP) exec -T backend bin/console doctrine:migrations:up-to-date --list-migrations --no-ansi 2>&1); \
	case "$$estado" in \
		*Out-of-date!*) ;; \
		*Up-to-date!*|*"previously executed"*) echo "Sin migraciones pendientes."; exit 0;; \
		*) echo "$$estado" >&2; exit 1;; \
	esac; \
	pendientes=$$(echo "$$estado" | sed -n 's/.*\(Version[0-9]\{14\}\).*not migrated.*/\1/p'); \
	ajenas=; for v in $$pendientes; do case "$$nuevas" in *" $$v "*) ;; *) ajenas="$$ajenas $$v";; esac; done; \
	if [ -n "$$ajenas" ]; then echo "No migro: hay pendientes que no llegaron después de $(desde):$$ajenas. Revisa doctrine:migrations:status (¿base rehecha sin anotar las versiones?)." >&2; exit 1; fi; \
	echo "Migraciones:" $$pendientes; \
	$(PROD_COMP) exec -T backend bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing

reiniciar: prod-env ## Producción: reinicia los contenedores de PHP para que tomen el código (unos segundos)
	$(PROD_COMP) up -d --no-build --force-recreate $(PROD_PHP)

front: ## Instala las dependencias del frontend (bun.lock, sin cambiarlo) y compila frontend/dist (lo sirve el contenedor sin reiniciar). Sin type-check: ese va en `npm run build`
	cd frontend && $(if $(shell command -v bun),bun,npx --yes bun@1) install --frozen-lockfile && npm run build-only

# El prerender le pide al backend el directorio de estaciones. En la VPS el certificado es solo del
# dominio (SERVER_NAME de .env.prod.local): por localhost falla el TLS y la página sale sin él.
# Un PAGINA_BACKEND del entorno tiene prioridad; sin .env.prod.local queda el valor del script.
PROD_DOMINIO = $(firstword $(shell sed -n 's/^SERVER_NAME=//p' .env.prod.local 2>/dev/null | tr ",\"'" '   '))

pagina: ## Compila la página web pública en backend/public/pagina
	cd pagina && $(if $(PAGINA_BACKEND),,$(if $(PROD_DOMINIO),PAGINA_BACKEND=https://$(PROD_DOMINIO))) npm run build

vendor: prod-env ## Instala las dependencias de PHP en backend/vendor (tras cambiar composer.lock)
	$(DOCKER) run --rm -v $(CURDIR)/backend:/app -w /app composer:2 install --no-interaction --no-progress --no-scripts --ignore-platform-reqs
	$(PROD_COMP) exec -T backend php bin/console assets:install public
	$(PROD_COMP) exec -T backend php bin/console importmap:install

imagen: prod-env ## Reconstruye las imágenes (solo si cambia un Dockerfile o una extensión de PHP) y recrea el stack
	$(PROD_COMP) up -d --build
	$(DOCKER) image prune -f

# Dev y prod se alternan en la VPS (mismos contenedores, misma base): `make vps` ↔ `make prod`.
# Las imágenes de dev llevan el prefijo dev- para no pisar las de prod; cada cambio solo recrea contenedores.
VPS_COMP = IMAGES_PREFIX=dev- APP_ENV=dev $(DOCKER_COMP) --env-file .env.vps.local -f compose.yaml -f compose.override.yaml -f compose.vps.yaml

vps-env:
	@test -f .env.vps.local || { echo "Falta .env.vps.local (secretos, ver compose.vps.yaml)" >&2; exit 1; }

vps: vps-env ## Pasa la VPS a dev, por el dominio de producción (secretos en .env.vps.local; `make prod` vuelve)
	$(VPS_COMP) up -d

vps-build: vps-env ## Reconstruye las imágenes de dev (si cambia un Dockerfile) y levanta dev
	$(VPS_COMP) up -d --build

modo: ## Dice si la VPS está en dev o en prod
	@i=$$($(DOCKER) inspect -f '{{.Config.Image}}' backend 2>/dev/null) || { echo apagado; exit 0; }; \
	case "$$i" in dev-*) echo dev;; *) echo prod;; esac

prod-logs: prod-env ## Sigue los logs de producción (uso: make prod-logs s=backend)
	$(PROD_COMP) logs -f --tail=200 $(s)

## —— Mapas 🗺️ (ADR-055) ————————————————————————————————————————————————
# Mapa base y rutas por carretera sin servicios externos. Los datos se bajan una vez y luego cada mes
# (Protomaps publica un build diario del planeta; Geofabrik, extractos diarios de OpenStreetMap).
# Corre igual en dev y en prod: los volúmenes y el contenedor de Valhalla son los mismos.
.PHONY: mapas valhalla valhalla-logs
mapas: ## Prepara el mapa base y el recorte de OpenStreetMap, y reinicia Valhalla para que reconstruya su grafo (MAPAS_BBOX, MAPAS_EXTRACTOS, MAPAS_MAXZOOM)
	$(DOCKER_COMP) --profile mapas build mapas
	$(DOCKER_COMP) --profile mapas run --rm mapas
	$(DOCKER_COMP) --profile mapas up -d --force-recreate valhalla
	@echo "Valhalla está construyendo su grafo (varios minutos): make valhalla-logs"

valhalla: ## Levanta Valhalla con el grafo que ya tiene (tras un `make down`)
	$(DOCKER_COMP) --profile mapas up -d valhalla

valhalla-logs: ## Sigue los logs de Valhalla (la construcción del grafo, al arrancar con un .pbf nuevo)
	$(DOCKER_COMP) --profile mapas logs -f --tail=100 valhalla

## —— Monitorización 📊 ——————————————————————————————————————————————————
MON_M=compose.monitoring.yaml
MON_UP     = $(DOCKER_COMP) -f $(MON_M)

mon-up: ## Start monitoring only (cAdvisor:8080, Prometheus:9090, Grafana:3000). Safe to run alongside `make dev`
	$(MON_UP) up --detach

mon-down: ## Stop monitoring only (app stack stays up)
	$(MON_UP) down

mon-logs: ## Show monitoring logs
	$(MON_UP) logs --tail=0 --follow
