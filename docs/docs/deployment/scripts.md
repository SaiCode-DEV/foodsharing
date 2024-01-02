---
sidebar_position: 3
---

# Scripts
All scripts can be run with `./scripts/[script]`.

## Daily use `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `start` or `init`                           | dev           | Boots up docker-containers and initialize the database (shorthand `containers-init`)  |
| `restart`                                   | dev           | Restarts the docker-containers and clears the assets-folder (shorthand `containers-restart`) |
| `stop [env]`                                | dev/test      | Stops the docker-containers (shorthand `containers-stop`) |
| `seed`                                      | dev           | Seeds the database with fake data and runs maintenance scripts (shorthand `db-seed`) |
| `clean [full]`                                     |               | Removes anything added by `start`/`test` scripts (expect docker-images); add `full` to remove docker images too |

## Codestyle `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `lint`                                      | dev           | Full code linting |
| `lint-js`                                   | dev           | `client`/`websocket` - Javascript code linting  |
| `lint-php`                                  | dev           | `src` - PHP code linting |
| `fix`                                       | dev           | Full code fixing |
| `fix-js`                                    | dev           | `client`/`websocket` - Javascript code fixing  |
| `fix-php`                                   | dev           | `src` - PHP code fixing |

## Testing `scripts`
| Script                | ENV  | Description                                                                                                   |
|-----------------------|------|---------------------------------------------------------------------------------------------------------------|
| `test`                | test | Without parameters, it runs all tests in the src folder and only initialize when no test container is running |
| `test [suite] [test]` | test | Runs a specific suite test example: `test Api BasketApiCest`                                                  |
| `test-js`             | test | Runs all tests in the `client` folder                                                                         |
| `test-websocket`      | test | Runs all tests in the `websocket` folder                                                                      |
| `test-e2e`            | dev  | Runs all playwright tests                                                                                     |
| `test e2e [test]`     | test | Runs a specific suite test example on playwright: `test bcard`                                                |



## Container `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `containers-build`                          | dev           | Builds the docker-containers |
| `containers-init`                           | dev           | Boots up docker-containers and initialize the database  |
| `containers-start`                          | dev           | Start the docker-containers and clears assets |
| `containers-restart`                        | dev           | Restarts the docker-containers and clears the assets-folder |
| `containers-stop [env]`                     | dev/test      | Stops the docker-containers |

## Database `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `db-init`                                   | dev           | Initialize the database, Seeds with fake data and runs maintenance scripts |
| `db-seed`                                   | dev           | Seeds the database with fake data and runs maintenance scripts |
| `db-drop [env]`                             | dev/test      | Drop the database |
| `db-dump`                                   | (dev)         | Run a `mysqldump` command in correct context |
| `db-run [command]`                          | (dev)         | Run a `mysql` command in correct context: `mysql foodsharing "select * from fs_foodsaver"` |

## DevDocs `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `db-docs-build`                             | (dev)         | Generates the database overview  |

## Other helping `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `symfony-console [command]`                 |               |  |
| `composer [command]`                        | (dev)         |  |
| `docker-compose [command]`                  | (dev)         |  |
| `run [command]`                             | dev           | Run a command inside the PHP docker  |
| `run-daily-maintenance`                     | dev           | Runs the daily maintenance, which is used to calculate stats and some database stuff |


---


## CI `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `ci-backend-build`                          | CI            | |
| `ci-backend-test`                           | CI            | |
| `ci-doc-build`                              | CI            | |
| `ci-notify-outdated`                        | CI            | |

## DEPLOYMENT `scripts`
| Script                                      | ENV           | Description                                                                           |
|---------------------------------------------|---------------|---------------------------------------------------------------------------------------|
| `deploy`                                    |               | |
| `deploy-generate_revision`                  |               | |
| `deploy-notify-slack`                       |               | |
| `deploy-websocket-restart`                  |               | |
