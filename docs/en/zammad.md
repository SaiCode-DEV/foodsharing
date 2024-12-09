# What is Zammad?

Zammad is an open-source helpdesk and customer support ticketing system that runs on self-hosted infrastructure.

## Integration with Foodsharing

The system includes a custom-built support form (accessible at `/support`) developed in Vue.js. 
The form communicates with Zammad's backend API through the Foodsharing REST API backend. This integration requires 
an API token for authentication. The backend uses the `zammad-api-client-php` library to handle all API interactions 
between Foodsharing and the Zammad ticketing system.

## Setup and Configuration

The admin user and API token are automatically created during container startup through 
the bash script `./scripts/db-zammad-seed`.

The setup process happens automatically when the Docker containers are launched, requiring no manual intervention.

## How to start docker container?

To start Zammad in your development environment, use:
```bash
ZAMMAD="true" ./scripts/start
```
After starting the container, Zammad is accessible at: http://localhost:18087

## Admin Login Credentials
- Username: admin@example.com
- Password: PasswOrd1!
