# Seed data

For generating a bit of initial data to play with, execute the seeding script:

```
./scripts/seed
```

It creates the two regions "Göttingen" and "Entenhausen". It former is a region with GOALS groups (working groups
with special permissions), which means that the ambassadors have restricted permissions unless they are also admins of
the GOALS groups. "Entenhausen" does not have GOALS groups, hence the ambassador can do almost anything.

By default, most users are members of "Göttingen". The password for all of them is `user`.

| Email                       | Role                                              |
|-----------------------------|---------------------------------------------------|
| `user1@example.com`         | Foodsharer                                        | 
| `user2@example.com`         | Foodsaver                                         |
| `storemanager1@example.com` | Store manager                                     |
| `storemanager2@example.com` | Store manager                                     |
| `userbot@example.com`       | Ambassador in Göttingen and admin of GOALS groups |
| `userbot2@example.com`      | Ambassador in Göttingen                           |
| `userbotreg2@example.com`   | Ambassador in Entenhausen                         |
| `userorga@example.com`      | Orgateam                                          |
| `userauth@example.com`      | OAuth manager                                     |

Users with workgroup functionality in "Göttingen":

| Email                                  | workgroup function |
|----------------------------------------|--------------------|
| `userwelcome1@example.com`             | Welcome            |
| `userwelcome2@example.com`             | Welcome            |
| `userwelcome3@example.com`             | Welcome            |
| `uservoting1@example.com`              | Voting             |
| `uservoting2@example.com`              | Voting             |
| `uservoting3@example.com`              | Voting             |
| `userfsp1@example.com`                 | foodshare point    |
| `userfsp2@example.com`                 | foodshare point    |
| `userstores1@example.com`              | Store coordination |
| `userstores2@example.com`              | Store coordination |
| `userstores3@example.com`              | Store coordination |
| `userreport1@example.com`              | Report             |
| `userreport2@example.com`              | Report             |
| `userreport3@example.com`              | Report             |
| `usermediation1@example.com`           | Mediation          |
| `usermediation2@example.com`           | Mediation          |
| `usermediation3@example.com`           | Mediation          |
| `userarbitration1@example.com`         | Arbitration        |
| `userarbitration2@example.com`         | Arbitration        |
| `userarbitration3@example.com`         | Arbitration        |
| `userfsmanagement1@example.com`        | FSManagement       |
| `userfsmanagement2@example.com`        | FSManagement       |
| `userfsmanagement3@example.com`        | FSManagement       |
| `userpr1@example.com`                  | PR                 |
| `userpr2@example.com`                  | PR                 |
| `userpr3@example.com`                  | PR                 |
| `usermoderation1@example.com`          | Moderation         |
| `usermoderation2@example.com`          | Moderation         |
| `usermoderation3@example.com`          | Moderation         |

Some users have additional permissions by being admins of global working groups:

| Email                                  | workgroup function |
|----------------------------------------|--------------------|
| `storemanager2@example.com`            | Support            |

Please refer to the [User Roles and Permissions](../../../glossary/roles-and-permissions) section for details on the different roles.  
*Tip: You can use private browser windows to log in with multiple users at the same time!*

The script also generates more dummy users and dummy data to fill the page with life (a bit at least).
Should you want to modify it, have a look at the file `/src/Dev/SeedCommand.php`.

Whenever you make changes to non-PHP frontend files (e.g. .vue, .js or .scss files), those are directly reflected in the running docker instance. Changes to PHP files will require a page reload in your browser.

To stop everything again, just run:

```
./scripts/stop
```

PHPMyAdmin is also included: [localhost:18081](http://localhost:18081). Log in with:

| Field    | Value |
|----------|-------|
| Server   | db    |
| Username | root  |
| Password | root  |

There you can directly look at and manipulate the data in the database
which can be necessary or very useful for manual testing and troubleshooting.

MailDev is also included: [localhost:18084](localhost:18084). There you can read all e-mails that you write via the front end.

## Seed data for tests

There is also a second seed script designated for the tests. You can find it in `/src/Dev/TestSeedCommand.php`. It
generates the minimal data that is required by most tests, like some of the base regions.
