package scheduler_bundle

import (
	"dagger.io/dagger"
	"universe.dagger.io/docker"
)

dagger.#Plan & {
	_vendorMount: "/srv/app/vendor": {
		dest: "/srv/app/vendor",
		type: "cache",
		contents: id: "vendor-cache"
	}

	client: {
		filesystem: "./": read: exclude: [".cloud/dagger", ".github", ".changelog", ".gitattributes", ".gitignore", "doc", "CONTRIBUTING.md", "README.md", "vendor"]
		env: APP_ENV: "test"
	}

	actions: {
		build: docker.#Build & {
			#Run: docker.#Run & {
				command: name: "composer"
			}
			steps: [
				docker.#Dockerfile & {
					source: client.filesystem."./".read.contents
					dockerfile: path: ".cloud/docker/Dockerfile"
				},
				#Run & {
					command: args: ["update", "--prefer-stable"]
					mounts: _vendorMount
				},
				#Run & {
					command: args: ["dump-autoload", "--optimize", "--classmap-authoritative"]
				},
			]
		}
		#Run: docker.#Run & {
			input: build.output
			mounts: _vendorMount
		}
		php_cs_fixer: #Run & {
			command: {
				name: "vendor/bin/php-cs-fixer"
				args: ["fix", "--allow-risky=yes", "--dry-run"]
			}
		}
		phpstan: #Run & {
			command: {
				name: "vendor/bin/phpstan"
				args: ["analyze", "--xdebug"]
			}
		}
	}
}
