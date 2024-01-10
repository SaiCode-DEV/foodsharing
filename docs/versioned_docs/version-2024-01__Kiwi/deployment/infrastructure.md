# Infrastructure
This section will describe our servers, where the pages are running.

## Hardware
The server is a root server running at and sponsored by our hosting sponsor [manitu.de](https://manitu.de).

Our Servers are:

| Server | Usage                | Stats                                                                                                 |
|--------|----------------------|-------------------------------------------------------------------------------------------------------|
| onion  | websites             | [munin](https://onion.foodsharing.network/foodsharing.network/onion.foodsharing.network/index.html)   |
| garlic | cloud, gitlab-runner | [munin](https://garlic.foodsharing.network/foodsharing.network/garlic.foodsharing.network/index.html) |

## Base System
We are running Debian on our Servers.
As filesystem we are using ZFS with different subvolumes, that are configured for the needs of the subvolume.
The System ist managed by ansible documented in the [foodsharing-ansible repository](https://gitlab.com/foodsharing-dev/foodsharing-ansible).

## Components
Running foodsharing requires different components, that are described in the following sections.

beta and production are running on the same database. Both are `prod` PHP applications, so `FS_ENV=prod` for beta and production.

~~~plantuml
@startumlRel(KubernetesBE2, 
scale max 1024 width
skinparam linetype polyline
skinparam nodesep 10
skinparam ranksep 200



' Azure
!define AzurePuml https://raw.githubusercontent.com/RicardoNiepel/Azure-PlantUML/release/2-1/dist

!includeurl AzurePuml/AzureCommon.puml
!includeurl AzurePuml/AzureSimplified.puml
!includeurl AzurePuml/Web/AzureWebApp.puml
!includeurl AzurePuml/Compute/AzureBatch.puml
!includeurl AzurePuml/Databases/AzureRedisCache.puml
!includeurl AzurePuml/Databases/AzureDatabaseForMariaDB.puml
!includeurl AzurePuml/DevOps/AzurePipelines.puml
!includeurl AzurePuml/Storage/AzureBlobStorage.puml

' Kubernetes
!define KubernetesPuml https://raw.githubusercontent.com/dcasati/kubernetes-PlantUML/master/dist

!includeurl KubernetesPuml/kubernetes_Context.puml
!includeurl KubernetesPuml/kubernetes_Simplified.puml

!includeurl KubernetesPuml/OSS/KubernetesIng.puml
!includeurl KubernetesPuml/OSS/KubernetesPod.puml

actor "User" as user
actor "Time" as time

left to right direction

' Components
Cluster_Boundary(cluster, "Server - onion") {
AzureDatabaseForMariaDB(sql, "Database", "")
AzureRedisCache(cache, "Redis", "")
AzureBlobStorage(storage, "Shared Folders\ndata\nimages\ntmp\nuploads", "")

Cluster_Boundary(nsWebSocket, "websocket") {
        AzureWebApp(webSocketServer, "NodeJS\ngit: production", "")
    }
    
    KubernetesIng(nginx, "Webserver", "")

    Cluster_Boundary(nsBackEndProd, "production") {
        AzureBatch(phpProd, "php-fpm\nFS_ENV=prod\ngit: production", "")
    }
    
    Cluster_Boundary(nsBackEndBeta, "beta") {
        AzureBatch(phpBeta, "php-fpm\nFS_ENV=prod\ngit: master", "")
    }
    
    AzureBatch(cron, "cronjob\ngit: production", "")
    AzureBatch(cronBounce, "bounce mail processing\ngit: production", "")
    AzureBatch(cronDaily, "daily tasks\ngit: production", "")
}
Rel(time, cron, "5 Minutes")
Rel(time, cronDaily, "0:15")
Rel(time, cronBounce, "30 Minutes")


Rel(nginx, phpProd, " ")
Rel(nginx, phpBeta, " ")
Rel(nginx, webSocketServer, " ")
Rel(nginx, storage, " ")


Rel(phpProd, sql, " ")
Rel(phpBeta, sql, " ")
Rel(webSocketServer, sql, " ")

Rel(phpProd, storage, " ")
Rel(phpBeta, storage, " ")

@enduml, " ")
Rel(phpProd, cache, " ")
Rel(phpBeta, cache, " ")
Rel(webSocketServer, cache, " ")

Rel(user, nginx, "HTTPS")
@enduml
~~~

### Webserver
As webserver we are running NGINX. The TLS Certificates are managed by acme.sh. 
The [TLS settings](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/webserver/templates/tls.conf) (Ciphers etc.) are serverwide.

There are two site configurations ([beta](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/foodsharing/templates/nginx-beta.conf) and [production](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/foodsharing/templates/nginx-production.conf)).
If changes are needed here you need to check if they can be applied before a deployment.
Talk to an admin for that.
If this is not possible create a [MR in the ansible repository](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/merge_requests) and let an admin merge both MRs with a short Maintenance time.   

### PHP
Both sites (beta and production) have a own pool running with php-fpm PHP Version and other Settings are defined in [ansible variables](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/group_vars/all.yml).

For the application there are also two different configuration files for [beta](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/foodsharing/templates/config.inc.beta.php) and [production](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/foodsharing/templates/config.inc.production.php).
If changes are needed here you need to check if they can be applied before a deployment.
Talk to an admin for that.
If this is not possible create a [MR in the ansible repository](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/merge_requests) and let an admin merge bot MRs with a short Maintenance time.

The fpm processes are running as a restricted user on the server.
NGINX is communicating through a unix socket with the processes.

### Database
We are using MariaDB as Database server. There are some settings defined in [foodsharing-ansible](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/foodsharing/templates/mariadb.cnf).
php-fpm is communication via IP (unix Socket is prepared) with MariaDB.

### Redis
There is a dedicated Redis instance running for both applications with a single Database. 
Both php processes and the websocket server are communicating over the network stack.

### Websocket Server
There is a nodeJS application running for the websocket server.
It is running as a systemd service on the production code as a separate user.
The nodeJS version is defined in [ansible variables](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/group_vars/all.yml).
If changes are needed here you need to check if they can be applied before a deployment.

### fs-mailqueuerunner
This service helps us delivering our Mails.
The Service is running as a [systemd Service](https://gitlab.com/foodsharing-dev/foodsharing-ansible/-/blob/master/roles/foodsharing/templates/fs-mailqueuerunner.service).

### cronjob
The command `bin/console foodsharing:cronjob` is running all 5 minutes.
Mainly this job is for fetching mails.

### process-bounce-emails
The command `bin/console foodsharing:process-bounce-emails` is running all 30 Minutes.
Bounce mails are fetched and used to mark the addresses in the database.

### daily tasks
The command `bin/console foodsharing:daily-cronjob` and `bin/console foodsharing:stats` are running every night.
At the cronjob the sleeping hats are renewed and notification mails for empty pickup slots are send.
At the stats command the pickup stats are renewed.
Further files older than 2 days are deleted from the tmp folder

## Deployment
The Application is deployed with [deployer](https://deployer.org/) from the GitLab CI.
The process is described in the [deploy.php](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/deploy.php).
If you change something here you should be very sure and talk to an admin before. 
Whenever there is new commit pushed to the branches `master` or `production` the CI is running and the version gets deployed.
If the maintenance mode is active in the new deployed version it will not be active anymore.
