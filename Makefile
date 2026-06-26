

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
	$(CONSOLE) $(if $(prod),--env=prod) app:migrar:todo $(if $(clean),--clean) $(if $(skip-estaticos),--skip-estaticos) $(if $(skip-iam),--skip-iam) $(if $(skip-config),--skip-config)  $(if $(boletos),--boletos=$(boletos)) $(foreach u,$(entities),--entities=$(u))

sincronizar: ## Sync new tickets from old FDN system
	@$(CONSOLE) app:sincronizar

reset-db: ## Reset database (drop, create, migrations, then migrate old data)
	@$(CONSOLE) app:reset-db  $(if $(soft),--soft,--hard)

test: ## Run all tests
	@$(DOCKER_COMP) exec backend vendor/bin/phpunit -c phpunit.dist.xml

testf: ## Run tests by name filter (usage: make testf F="testMethodName")
	@$(DOCKER_COMP) exec backend vendor/bin/phpunit -c phpunit.dist.xml --filter "$(F)"

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
	SERVER_NAME=:80 MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up --build  -d
debug:
	SERVER_NAME=:80 MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up -d
	
dev:
	SERVER_NAME=http://localhost MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure APP_ENV=dev $(DOCKER_COMP) up -d

restart_debug:
	$(DOCKER_COMP) down --remove-orphans && XDEBUG_MODE=debug  APP_ENV=dev $(DOCKER_COMP) up -d

prod:
	SERVER_NAME=transportesfuentedelnorte.com APP_SECRET=fdn.transportesfuentedelnorte.com CADDY_MERCURE_JWT_SECRET=fdn.transportesfuentedelnorte.com $(DOCKER_COMP)  -f docker-compose.yml -f docker-compose.prod.yml up -d

prod-build:
	SERVER_NAME=transportesfuentedelnorte.com  APP_SECRET=fdn.transportesfuentedelnorte.com CADDY_MERCURE_JWT_SECRET=fdn.transportesfuentedelnorte.com $(DOCKER_COMP)  -f docker-compose.yml -f docker-compose.prod.yml up --build -d


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
