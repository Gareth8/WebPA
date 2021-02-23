<?php
/**
 * ResultHandler
 *
 * @copyright Loughborough University
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPL version 3
 *
 * @link https://github.com/webpa/webpa
 */

namespace WebPA\includes\classes;

use Doctrine\DBAL\ParameterType;
use WebPA\includes\functions\Common;

class ResultHandler
{
    private $_DAO;
    private $_assessment;
    private $_collection;
    private $_collection_id;
    private $dbConn;
    private $moduleId;

    /*
    * CONSTRUCTOR for the result handler
    * @param mixed $DAO
    */
    function __construct(DAO $DAO)
    {
        $this->moduleId = Common::fetch_SESSION('_module_id', null);

        $this->_DAO = $DAO;
        $this->dbConn = $this->_DAO->getConnection();
    }

    /*
    * ================================================================================
    * Public Methods
    * ================================================================================
    */

    /*
    * --------------------------------------------------------------------------------
    * Accessor Methods
    * --------------------------------------------------------------------------------
    */

    /**
     * Set Assessment object
     * @param object $assessment assessment object to use
     */
    function set_assessment(&$assessment)
    {
        $this->_assessment =& $assessment;

        $this->_collection_id = $this->_assessment->get_collection_id();

        $_group_handler = new GroupHandler();

        $this->_collection = $_group_handler->get_collection($this->_collection_id);
    }

    /**
     * Return the Assessment object being used
     * @return object assessment object used
     */
    function & get_assessment()
    {
        return $this->_assessment;
    }// /->get_assessment()

    /**
     * Fetch all responses for this assessment
     * @return array array of the responses for the given assessment
     */
    function get_responses()
    {
        $query =
            'SELECT um.group_id, um.user_id, um.marked_user_id, um.question_id, um.score ' .
            'FROM ' . APP__DB_TABLE_PREFIX . 'user_mark um ' .
            'INNER JOIN ' . APP__DB_TABLE_PREFIX . 'assessment a ' .
            'ON um.assessment_id = a.assessment_id ' .
            'WHERE um.assessment_id = ? ' .
            'AND a.collection_id = ? ' .
            'ORDER BY um.group_id, um.user_id, um.marked_user_id, um.question_id';

        return $this->dbConn->fetchAllAssociative($query, [$this->_assessment->id, $this->_collection_id], [ParameterType::STRING, ParameterType::STRING]);
    }

    /**
     * Function to get the marks fot the assessment
     * @return integer number of users
     */
    function get_responses_count_for_assessment()
    {
        $responseCountsQuery =
            'SELECT COUNT(DISTINCT um.user_id) ' .
            'FROM ' . APP__DB_TABLE_PREFIX . 'user_mark um ' .
            'INNER JOIN ' . APP__DB_TABLE_PREFIX . 'assessment a ' .
            'ON um.assessment_id = a.assessment_id ' .
            'WHERE um.assessment_id = ? ' .
            'AND a.collection_id = ?';

        return $this->dbConn->fetchOne($responseCountsQuery, [$this->_assessment->id, $this->_collection->id], [ParameterType::STRING, ParameterType::STRING]);
    }

    /**
     * Function to get the number of students who have responded to the assessment
     * @return array all the user ids of those who has responded
     */
    function get_responded_users()
    {
        $usersThatRespondedQuery =
            'SELECT DISTINCT um.user_id ' .
            'FROM ' . APP__DB_TABLE_PREFIX . 'user_mark um ' .
            'INNER JOIN ' . APP__DB_TABLE_PREFIX . 'assessment a ' .
            'ON um.assessment_id = a.assessment_id ' .
            'WHERE um.assessment_id = ? ' .
            'AND a.collection_id = ? ' .
            'ORDER BY um.user_id';

        return $this->dbConn->fetchFirstColumn($usersThatRespondedQuery, [$this->_assessment->id, $this->_collection->id], [ParameterType::STRING, ParameterType::STRING]);
    }

    /**
     * Fetch a count of the responses for the given group
     * @param integer $group_id
     * @return integer count of the responses
     */
    function get_responses_count($group_id)
    {
        if ($this->_collection->group_id_exists($group_id)) {
            $responseCountQuery =
                'SELECT COUNT(DISTINCT user_id) ' .
                'FROM ' . APP__DB_TABLE_PREFIX . 'user_mark um ' .
                'INNER JOIN ' . APP__DB_TABLE_PREFIX . 'assessment a ' .
                'ON um.assessment_id = a.assessment_id ' .
                'WHERE um.assessment_id = ? ' .
                'AND a.collection_id = ? ' .
                'AND um.group_id = ?';

            return $this->dbConn->fetchOne($responseCountQuery, [$this->_assessment->id, $this->_collection->id, $group_id], [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        } else {
            return null;
        }
    }// /->get_responses_count()

    /**
     * Fetch a count of the responses for all this user's assessments (that opened this academic year)
     * @param integer $user_id
     * @param date $year
     * @return array
     */
    function get_responses_count_for_user($user_id, $year = NULL)
    {
        $sql = "SELECT a.assessment_id, COUNT(DISTINCT um.user_id)
      FROM " . APP__DB_TABLE_PREFIX . "assessment a
        LEFT JOIN " . APP__DB_TABLE_PREFIX . "user_mark um ON a.assessment_id = um.assessment_id
          AND a.module_id = {$this->moduleId} ";
        if (!empty($year)) {
            $next_year = $year + 1;
            $month = strval(APP__ACADEMIC_YEAR_START_MONTH);
            if (APP__ACADEMIC_YEAR_START_MONTH < 10) {
                $month = '0' . $month;
            }
            $sql .= "AND a.open_date >= '{$year}-{$month}-01 00:00:00'
          AND a.open_date < '{$next_year}-{$month}-01 00:00:00' ";
        }
        $sql .= 'GROUP BY assessment_id';
        return $this->_DAO->fetch_assoc($sql);
    }// /->get_responses_count_for_user()

    /**
     * Fetch a count of the members for all this user's assessments (that opened this academic year)
     * @param integer $user_id
     * @param date $year
     * @return array assoc array ( assessment_id => member_count )
     */
    function get_members_count_for_user($user_id, $year = NULL)
    {
        $sql = "SELECT a.assessment_id, COUNT(DISTINCT ugm.user_id)
      FROM " . APP__DB_TABLE_PREFIX . "assessment a
        LEFT JOIN " . APP__DB_TABLE_PREFIX . "user_group ug ON a.collection_id = ug.collection_id
        LEFT JOIN " . APP__DB_TABLE_PREFIX . "user_group_member ugm ON ug.group_id = ugm.group_id
          AND a.module_id = {$this->moduleId} ";
        if (!empty($year)) {
            $next_year = $year + 1;
            $month = strval(APP__ACADEMIC_YEAR_START_MONTH);
            if (APP__ACADEMIC_YEAR_START_MONTH < 10) {
                $month = '0' . $month;
            }
            $sql .= "AND a.open_date >= '{$year}-{$month}-01 00:00:00'
          AND a.open_date < '{$next_year}-{$month}-01 00:00:00' ";
        }
        $sql .= 'GROUP BY a.assessment_id';
        return $this->_DAO->fetch_assoc($sql);
    }// /->get_members_count_for_user()

    /**
     * Has the user submitted marks for the given assessment already
     * @param string $user_id user to check
     * @param string $assessment_id assessment to check
     * @return boolean  has the user responded
     */
    function user_has_responded($user_id, $assessment_id)
    {
        $numberOfResponsesQuery =
            'SELECT COUNT(DISTINCT um.marked_user_id) ' .
            'FROM ' . APP__DB_TABLE_PREFIX . 'assessment a ' .
            'LEFT JOIN ' . APP__DB_TABLE_PREFIX . 'user_mark um ' .
            'ON a.assessment_id = um.assessment_id ' .
            'AND a.assessment_id = ? ' .
            'AND um.user_id = ?';

        $num_responses = $this->dbConn->fetchOne($numberOfResponsesQuery, [$assessment_id, $user_id], [ParameterType::STRING, ParameterType::INTEGER]);

        return $num_responses > 0;
    }

    /**
     * Function for the assessments taken by a user
     * @param string $user_id
     */
    function assessments_taken_by_user($user_id)
    {

    }

    /*
    * ================================================================================
    * Private Methods
    * ================================================================================
    */

}// /class: ResultHandler

?>
