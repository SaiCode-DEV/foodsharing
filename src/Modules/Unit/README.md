# Module Unit

The foodsharing platform use different simular types to manages groups and regions.
This module combines the basic structure of them into a unit.
This allows to reuse the code which access to the table 'fs_bezirk' which is used by both system.
This is only a internal module so that the group and regions specific parts can base on top of it.


~~~{plantuml}
class City
class District
class Region
class FederalState
class Country
class WorkingGroup
class BigCity
class PartOfTown

Country -d- FederalState
FederalState -d- City
FederalState -d- District
FederalState -d- Region 
FederalState -d- BigCity
BigCity -d- PartOfTown
~~~

~~~{plantuml}
class WELCOME
class VOTING
class FSP
class STORES_COORDINATION
class REPORT
class ARBITRATION
class MEDIATION
class FSMANAGEMENT
class PR
class MODERATION
class BOARD
class ELECTION

WorkingGroup -d- WELCOME
WorkingGroup -d- VOTING
WorkingGroup -d- FSP
WorkingGroup -d- STORES_COORDINATION
WorkingGroup -d- REPORT
WorkingGroup -d- ARBITRATION
WorkingGroup -d- MEDIATION
WorkingGroup -d- FSMANAGEMENT
WorkingGroup -d- PR
WorkingGroup -d- MODERATION
WorkingGroup -d- BOARD
WorkingGroup -d- ELECTION
~~~
