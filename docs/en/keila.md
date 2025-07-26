# Keila

[Keila](https://www.keila.io/de) is an open-source software for sending newsletters. We use it as a replacement for the
previous newsletter tool that was included in the web page.

## Integration with Foodsharing

Keila is run in its own docker container. A second container provides the PostgreSQL database that Keila uses, which is
separate from our usual database. For sending emails, Keila is configured manually to use the same SMTP host as the
[mailqueuerunner](./deployment/infrastructure#fs-mailqueuerunner) container. The list of newsletter subscribers is 
synchronised from our database to Keila by the daily maintenance.

## How to start docker container?

To start Keila in your development environment, use:
```bash
env KEILA=true ./scripts/start
```
After starting the container, Keila is accessible at: http://localhost:18088. The admin login is
- Username: root@localhost
- Password: rootpassword

## Configuration in the development environment

First create a new project in Keila. To allow the synchronisation of subscribers to Keila, create an API token in the
project (the name of which does not matter) and copy that into the `KEILA_TOKEN` constant in `config.inc.dev.php`. Now
run `./scripts/run-daily-maintenance`. After this, you should be able to see a list of contacts in Keila.

For sending newsletters, create a sender in the Keila project with
- SMTP host "maildev"
- port 1025
- user "user"
- password "pass"
- no TLS or SSL

Now you can create a new campaign and send it. The emails should show up in maildev at http://localhost:18084.
