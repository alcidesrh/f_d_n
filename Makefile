

# Parameters
SHELL         = sh
PROJECT       = strangebuzz
GIT_AUTHOR    = COil
HTTP_PORT     = 8000

# Executables
EXEC_PHP      = php
COMPOSER      = composer
REDIS         = redis-cli
GIT           = git
YARN          = yarn
NPX           = npx
EXEC_SYMFONY  = symfony console
# Alias
SYMFONY       = $(EXEC_PHP) bin/console
SYMFONY_BIN   = symfony
DOCKER        = docker
DOCKER_COMP   = docker compose
CONSOLE       = $(DOCKER_COMP) exec backend bin/console


# —— Backend Symfony (inside container) ——————————————————————————————————————
cc: ## Clear the cache (inside container)
	@$(CONSOLE) cache:clear

warmup: ## Warmup the cache (inside container)
	@$(CONSOLE) cache:warmup

migration: ## Create a new migration
	@$(CONSOLE) make:migration

migrate: ## Run pending migrations
	@$(CONSOLE) doctrine:migrations:migrate -n

entity-setup: ## Sync dynamic entity config metadata
	@$(CONSOLE) app:config:sync-metadata

entity: ## Create/modify an entity
	@$(CONSOLE) make:entity

# migrar: ## Migrate tickets from old FDN system (usage: make migrar C=500 R=1)
# 	$(CONSOLE) app:migrar $(if $(C),$(C),100) $(if $(R),--r)

migrar-entities: ## make migrar-entities entities='Empresa Localidad Estacion Piloto Marca Bus Asiento cliente usuario trayecto tarifa'
	$(CONSOLE) app:migrar:estaticos $(if $(entities),$(entities))

.PHONY: migrar-todo
migrar-todo:
	$(CONSOLE) $(if $(prod),--env=prod) app:migrar:todo $(if $(clean),--clean) $(if $(conservar),--conservar) $(if $(estaticos),--estaticos) $(if $(iam),--iam) $(if $(config),--config) $(if $(data),--data) $(if $(boletos),boletos=$(boletos)) $(foreach u,$(entities),--entities=$(u))

sincronizar: ## Sincronización con el legado (ADR-042): un ciclo a mano (el programador la corre cada minuto)
	@$(CONSOLE) app:sincronizar

tareas: ## Programador de tareas (ADR-044): estado de cada tarea; `make tareas t=reportes_diarios` la pone en cola ya
	@$(CONSOLE) app:programador:tareas $(if $(t),--ejecutar=$(t))

reset-db: ## Reset database (drop, create, migrations, then migrate old data)
	@$(CONSOLE) app:reset-db  $(if $(soft),--soft,--hard)

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

build: ## Builds the images (php + caddy)
	$(DOCKER_COMP) build --pull --no-cache

down: ## Stop the docker hub
	$(DOCKER_COMP) down --remove-orphans

check: ## Docker check
	@$(DOCKER) info > /dev/null 2>&1                                                                   # Docker is up
	@test '"healthy"' = `$(DOCKER) inspect --format "{{json .State.Health.Status }}" strangebuzz-db-1` # Db container is up and healthy

# Snippet L126+2 => templates/snippet/code/_135.html.twig

sh: ## Log to the docker container
	@$(DOCKER_COMP) exec backend sh

sqlserver: ## Log to the docker container
	@$(DOCKER_COMP) exec sqlserver_php sh

kill: ## Force Stop containers
	@$(DOCKER_COMP) kill

start: ## Stop containers
	@$(DOCKER_COMP) start

stop: ## Stop containers
	@$(DOCKER_COMP) kill

restart: ## restart containers
	@$(DOCKER_COMP) restart

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

bash: ## Connect to the application container
	@$(DOCKER) exec -it backend bash

commands: ## Display all commands in the project namespace
	@$(SYMFONY) list $(PROJECT)

##—————————————————————————————————————————————————————————————————
stats: ## Commits by the hour for the main author of this project
	@$(GIT) log --author="$(GIT_AUTHOR)" --date=iso | perl -nalE 'if (/^Date:\s+[\d-]{10}\s(\d{2})/) { say $$1+0 }' | sort | uniq -c|perl -MList::Util=max -nalE '$$h{$$F[1]} = $$F[0]; }{ $$m = max values %h; foreach (0..23) { $$h{$$_} = 0 if not exists $$h{$$_} } foreach (sort {$$a <=> $$b } keys %h) { say sprintf "%02d - %4d %s", $$_, $$h{$$_}, "*"x ($$h{$$_} / $$m * 50); }'
##———————————————————————————————————————————————————————————————————
## --Propios--------------------------

b: ## Builds the images (php + caddy)
	$(DOCKER_COMP) build --pull --no-cache

d: ## Stop the docker hub
	$(DOCKER_COMP) down --remove-orphans



db_port: ## up with xdebug
	symfony var:export --multiline | grep DATABASE_SERVER

debug-build:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up --build  -d
debug:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up -d
dev-build:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure APP_ENV=dev $(DOCKER_COMP) up --build  -d
dev:
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure APP_ENV=dev $(DOCKER_COMP) up -d

restart_debug:
	$(DOCKER_COMP) down --remove-orphans && XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up -d

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
.PHONY: deploy reiniciar front pagina vendor imagen
PROD_PHP = backend worker worker-pesadas programador

deploy: prod-env ## Actualiza producción: git pull y, según lo que cambió, vendor, front, página, imagen y reinicio de PHP
	@antes=$$(git rev-parse HEAD); git pull --ff-only; \
	cambio() { git diff --quiet "$$antes" HEAD -- "$$@" || return 0; return 1; }; \
	if cambio backend/composer.lock; then $(MAKE) -s vendor; fi; \
	if cambio frontend; then $(MAKE) -s front; fi; \
	if cambio pagina frontend/src; then $(MAKE) -s pagina; fi; \
	if cambio backend/Dockerfile frontend/Dockerfile; then $(MAKE) -s imagen; else $(MAKE) -s reiniciar; fi

reiniciar: prod-env ## Producción: reinicia los contenedores de PHP para que tomen el código (unos segundos)
	$(PROD_COMP) up -d --no-build --force-recreate $(PROD_PHP)

front: ## Compila el frontend en frontend/dist (lo sirve el contenedor sin reiniciar). Sin type-check: ese va en `npm run build`
	cd frontend && npm run build-only

pagina: ## Compila la página web pública en backend/public/pagina
	cd pagina && npm run build

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

vps: vps-env ## Pasa la VPS a dev, accesible por su IP pública (secretos en .env.vps.local; `make prod` vuelve)
	$(VPS_COMP) up -d

vps-build: vps-env ## Reconstruye las imágenes de dev (si cambia un Dockerfile) y levanta dev
	$(VPS_COMP) up -d --build

modo: ## Dice si la VPS está en dev o en prod
	@i=$$($(DOCKER) inspect -f '{{.Config.Image}}' backend 2>/dev/null) || { echo apagado; exit 0; }; \
	case "$$i" in dev-*) echo dev;; *) echo prod;; esac

prod-logs: prod-env ## Sigue los logs de producción (uso: make prod-logs s=backend)
	$(PROD_COMP) logs -f --tail=200 $(s)

sf_sqlserver:
	@$(DOCKER_COMP) exec sqlserver_php sh
# schema:
# 	@$(DOCKER_COMP) exec backend bin/console api:graphql:export > ./pwa/graphql/documents/schema.graphql

schema:
	@$(DOCKER_COMP) exec backend bin/console api:graphql:export > ./api/public/schema.graphql
	
frontend-fdn-quasar-restart:
	@$(DOCKER_COMP) restart frontend-fdn-quasar

migrar:
	@$(DOCKER_COMP) exec backend bin/console --env=prod app:migrar:todo --clean 100
	
clean:
	@$(DOCKER_COMP) exec backend bin/console --env=prod app:reset-db --hard

## —— Monitorización 📊 ——————————————————————————————————————————————————
MON_O=compose.override.yaml
MON_M=compose.monitoring.yaml
MON_UP     = $(DOCKER_COMP) -f $(MON_M)
MON_FULL   = $(DOCKER_COMP) -f compose.yaml -f $(MON_O) -f $(MON_M)

mon-up: ## Start monitoring only (cAdvisor:8080, Prometheus:9090, Grafana:3000). Safe to run alongside `make dev`
	$(MON_UP) up --detach

mon-down: ## Stop monitoring only (app stack stays up)
	$(MON_UP) down

mon-logs: ## Show monitoring logs
	$(MON_UP) logs --tail=0 --follow

mon-restart: ## Restart monitoring
	$(MON_UP) restart

mon-debug: ## Start full stack (app + monitoring) with Xdebug
	SERVER_NAME=localhost MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure XDEBUG_MODE=debug  APP_ENV=dev $(MON_FULL) up --detach
