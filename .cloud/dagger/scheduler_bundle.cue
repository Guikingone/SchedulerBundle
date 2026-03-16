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
		#Composer: docker.#Run & {
			command: name: "composer"
		}
		composer: docker.#Pull & {
			source: "composer"
		}
		[tag=string]: {
			versions: {
				"8.0": _,
				"8.1": _,
				build: docker.#Build & {
					#Run: docker.#Run & {
						input: build.output
						mounts: _vendorMount
					}
					steps: [
						docker.#Pull & {
							source: "php:\(tag)"
						},
						docker.#Copy & {
							input: composer.output
							contents: build.output
							path: "/usr/bin/composer"
						},
						#Composer & {
							command: args: ["update", "--prefer-stable"]
							mounts: _vendorMount
						},
						#Composer & {
							command: args: ["dump-autoload", "--optimize", "--classmap-authoritative"]
						},
					]
				}
			}
		}
	}
}
