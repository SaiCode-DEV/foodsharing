/**
 * Equivalent to PHP class Foodsharing\Modules\Core\DBConstants\Region\RegionIDs
 */
enum RegionIDs {
  // upper level holding groups
  ROOT = 0,
  GLOBAL_WORKING_GROUPS = 392,
  EUROPE_WELCOME_TEAM = 813,
  EUROPE = 741, // second level from top. First selectable level
  FOODSHARING_ON_FESTIVALS = 1432,

  // countries
  GERMANY = 1,
  AUSTRIA = 63,
  SWITZERLAND = 106,

  // special groups
  QUIZ_AND_REGISTRATION_WORK_GROUP = 341,
  PR_PARTNER_AND_TEAM_WORK_GROUP = 1811,
  TEAM_BOARD_MEMBER = 1373,
  TEAM_ALUMNI_MEMBER = 1564,
  TEAM_ADMINISTRATION_MEMBER = 1565,
}

export default RegionIDs;
