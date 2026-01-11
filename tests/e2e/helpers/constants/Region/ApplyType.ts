/**
 * Equivalent to PHP class Foodsharing\Modules\Core\DBConstants\Region\ApplyType
 * Only valid for working groups
 * TINYINT(2) | NOT NULL DEFAULT '2'.
 */
enum ApplyType {
  /** No one can apply for this working group */
  NOBODY = 0,
  /** Special requirements have to be fulfilled in order to apply */
  REQUIRES_PROPERTIES = 1,
  /** Everybody can apply for this working group (default) */
  EVERYBODY = 2,
  /** The working group is open and does not need application */
  OPEN = 3,
}

export default ApplyType;
