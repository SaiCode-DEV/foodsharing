# Store permissions

The following tables are a reference for the intended permissions of users in stores, i.e. the permissions as they should be. This does not take into account if the code currently follows these rules. Ideally, unit tests should eventually cover all of these permissions. Definitions of the listed roles can be found in [Glossary/Roles and permissions](../glossary/roles-and-permissions).

General remarks:
- Store coordination groups supersede ambassadors. This means that in regions with such a group, the region's ambassadors do not have special permissions in stores and are being treated like everyone else depending on their store membership (normal team member, stand-by list, or store coordinator).

## Wall
| User | Status |  Can see | Can post | Can delete everything | Can delete own post | Can delete older than month |
| -------- | -------- | -------- | -------- | -------- | -------- | -------- |
| orgUser | is not part of team    |   yes  |  yes | yes | yes | yes |
| orgUser | is part of team   | yes  | yes | yes | yes | yes |
| ambassador | in district of store is not part of team | yes | yes | no | yes | yes|
| ambassador | in district of store is part of team | yes | yes | no | yes | yes|
| ambassador | not in district of store is not part of team | no | no | no | no | no |
| ambassador | not in district of store is part of team | yes | yes | no | yes | no |
| admin of store coordination | in district of store is not part of team | yes | yes | no | yes | yes|
| admin of store coordination | in district of store is part of team | yes | yes | no | yes | yes|
| admin of store coordination | not in district of store is not part of team | no | no | no | no | no |
| admin of store coordination | not in district of store is part of team | yes | yes | no | yes | no |
| store manager | store manager | yes | yes | no | yes | yes|
| foodsaver | active member | yes | yes | no | yes | no |
| foodsaver | jumper | no | no | no | no | no |
| foodsaver | unverified user | no | no | no | no | no |
| foodsaver | unverified and jumper | no | no | no | no | no |

## Team list
| User | Status | Can see | Can use BV mode | Can see phone numbers | Can add users | can edit jumpers (add / remove) | can edit store manager (add / remove) |
| -------- | -------- | -------- | -------- | -------- | -------- | -------- | -------- |
| orgUser | is not part of team    |   yes  |  yes | yes | yes | yes | yes |
| orgUser | is part of team   | yes | yes | yes | yes | yes | yes |
| ambassador | in district of store is not part of team | yes | yes | yes | yes | yes | yes |
| ambassador | in district of store is part of team | yes | yes | yes | yes | yes | yes |
| ambassador | not in district of store is not part of team| no | no | no | no | no | no |
| ambassador | not in district of store is part of team | yes | no | yes | no | no | no | no |
| admin of store coordinator group | in district of store is not part of team | yes | yes | yes | yes | yes | yes |
| admin of store coordinator group | in district of store is part of team | yes | yes | yes | yes | yes | yes |
| admin of store coordinator group | not in district of store is not part of team| no | no | no | no | no | no |
| admin of store coordinator group | not in district of store is part of team | yes | no | yes | no | no | no | no |
| store manager | store manager | yes | yes | yes | yes | yes | yes |
| foodsaver | active member | yes | no | yes | no | no | no |
| foodsaver |  jumper | yes | no | no | no | no | no |
| foodsaver |  unverified user | yes  | no | no | no | no | no |
| foodsaver |  unverified and jumper | yes | no |  no | no | no | no |

## Pickup list

| User | Status  | Can see | Can add and remove pickups | Can sign in and out themselves | Can sign out others | can approve pickup |
| -------- | -------- | -------- | -------- | -------- | -------- |-------- |
| orgUser | is not part of team    | yes | yes | yes | yes | yes |
| orgUser | is part of team   | yes | yes | yes | yes | yes |
| ambassador | in district of store is not part of team | yes | yes | yes | yes | yes |
| ambassador | in district of store is part of team | yes | yes | yes | yes | yes |
| ambassador | not in district of store is not part of team| no |no |no |no |no |
| ambassador | not in district of store is part of team | yes | no |  yes |  no |  no |
| admin of store coordinator group | in district of store is not part of team | yes | yes | yes | yes | yes |
| admin of store coordinator group | in district of store is part of team | yes | yes | yes | yes | yes |
| admin of store coordinator group | not in district of store is not part of team| no |no |no |no |no |
| admin of store coordinator group | not in district of store is part of team | yes | no |  yes |  no |  no |
| store mananger|store manager | yes | yes | yes | yes | yes |
| foodsaver | active member | yes | no | yes | no | no |
| foodsaver | jumper | no |  no | no | no | no |
| foodsaver | unverified user | no | no | no | no | no |
| foodsaver | unverified and jumper | no | no | no | no | no |

## Options

| User | Status  | Can use team chat | Can use jumper chat | Store Information (Button) |
| -------- | -------- | -------- | -------- | -------- |
| orgUser | is not part of team |  no | no | yes |
| orgUser | is part of team   | yes | no | yes |
| ambassador | in district of store is not part of team| no | no | yes |
| ambassador | in district of store is part of team | yes | no | yes |
| ambassador | not in district of store is not part of team| no | no | yes |
| ambassador | not in district of store is part of team | yes| yes | yes |
| admin of store coordinator group | in district of store is not part of team| no | no | yes |
| admin of store coordinator group | in district of store is part of team | yes | no | yes |
| admin of store coordinator group | not in district of store is not part of team| no | no | yes |
| admin of store coordinator group | not in district of store is part of team | yes| yes | yes |
| store manager | store manager | yes | yes | yes |
| foodsaver | active member | yes | no | yes |
| foodsaver | jumper | no  | yes | yes |
| foodsaver | unverified user | no | no | yes |
| foodsaver | unverified and jumper | no | yes | yes |

## Store information modal

| User | Status  | Can see particularities | Can edit particularities | Can see regular pickups | Can edit regular pickups |
| -------- | -------- | -------- | -------- | -------- | -------- |
| orgUser | is not part of team |  yes | yes | yes | yes |
| orgUser | is part of team   | yes | yes | yes | yes |
| ambassador | in district of store is not part of team| yes | yes | yes | yes |
| ambassador | in district of store is part of team | yes | yes | yes | yes |
| ambassador | not in district of store is not part of team| ? | no | no | no |
| ambassador | not in district of store is part of team | yes| no | yes | no |
| admin of store coordinator group | in district of store is not part of team| yes | yes | yes | yes |
| admin of store coordinator group | in district of store is part of team | yes | yes | yes | yes |
| admin of store coordinator group | not in district of store is not part of team| ? | no | no | no |
| admin of store coordinator group | not in district of store is part of team | yes| no | no | no |
| store manager | store manager | yes | yes | yes | yes |
| foodsaver | active member | yes | no | yes | no |
| foodsaver | jumper | yes  | no | no | no |
| foodsaver | unverified user | yes | no | no | no |
| foodsaver | unverified and jumper | yes | no | no | no |

## Information

| User | Status  | Can see |
| -------- | -------- | -------- |
| orgUser | is not part of team | yes |
| orgUser | is part of team   | yes |
| ambassador | in district of store is not part of team| yes |
| ambassador | in district of store is part of team | yes |
| ambassador | not in district of store is not part of team| no |
| ambassador | not in district of store is part of team | yes |
| admin of store coordinator group | in district of store is not part of team| yes |
| admin of store coordinator group | in district of store is part of team | yes |
| admin of store coordinator group | not in district of store is not part of team| no |
| admin of store coordinator group | not in district of store is part of team | yes |
| store manager | store manager | yes |
| foodsaver | active member | yes |
| foodsaver | jumper | yes |
| foodsaver | unverified user | yes |
| foodsaver | unverified and jumper | yes |

### TODO / open questions for future
- what happens to Orga, ambassadors, store managers, admins of store coordinator group if they are not verified
- Botschafter sollen sich nicht selbst verifizieren dürfen
- Verifizierungstatus ist stärker als die Rolle
