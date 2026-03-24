# Listmonk

[Listmonk](https://listmonk.app) is an open-source software for sending newsletters.
We use it as a replacement for the previous newsletter tool that was included in the web page.

## Integration with Foodsharing

Listmonk is run in its own docker container. A second container provides the PostgreSQL
database that Listmonk uses, which is separate from our usual database.
For sending emails, Listmonk is configured manually to use the same SMTP host
as the [mailqueuerunner](./deployment/infrastructure#fs-mailqueuerunner) container.

Users can initially subscribe to the newsletter when creating their account.
Logged-in users can check their subscription status and (un-)subscribe at any time
from their profile settings.
Users can choose to unsubscribe while deleting their account.

The backend communicates with Listmonk via the `src/Lib/ListmonkClient.php` class.

## How to start docker container?

To start Listmonk in your development environment, use:
```bash
env LISTMONK=true ./scripts/start
```
After starting the container, Listmonk is accessible at: http://localhost:18088. The admin login is
- Username: `listmonk`
- Password: `listmonk`

## Configuration in the development environment

Log in to listmonk at http://localhost:18088 as admin:
- Username: `listmonk`
- Password: `listmonk`

Create a user at "Users > Users":
- Type: API
- Username: `subscriber-sync`
- User role: `Super Admin`

Click *Save* and **copy the generated token** into the `LISTMONK_TOKEN` constant in `config.inc.dev.php`.

For sending newsletters, configure SMTP in "Settings > Settings > SMTP"
- SMTP host: "maildev"
- Port: `1025`
- Auth protocol: `LOGIN`
- Username: `user`
- Password: `pass`
- TLS: `off`

Now you can create a new campaign and send it. The emails should show up in maildev at http://localhost:18084.
