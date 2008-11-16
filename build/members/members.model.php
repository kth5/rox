<?php


class MembersModel extends RoxModelBase
{
    
    private $profile_language = null;
    
    public function getMemberWithUsername($username)
    {
        $username = mysql_real_escape_string($username);
        if ($values = $this->singleLookup_assoc(
            "
SELECT *
FROM members
WHERE Username = '$username'
            "
        )) {
            return new Member($values, $this->dao);
        } else {
            return false;
        }
    }
    
    public function getMemberWithId($id)
    {
        $id = (int)$id;
        if ($values = $this->singleLookup_assoc(
            "
SELECT *
FROM members
WHERE id = $id
            "
        )) {
            return new Member($values, $this->dao);
        } else {
            return false;
        }
    }


    /**
     * set the location of a member
     */
    public function setLocation($IdMember,$geonameid = false)
    {
        $IdMember = (int)$IdMember;
        $geonameid = (int)($geonameid);
        
        $errors = array();
        
        if (empty($IdMember)) {
            // name is not set:
            $errors['Name'] = 'Name not set';
        }
        if (empty($geonameid)) {
            // name is not set:
            $errors['Geonameid'] = 'Geoname not set';
        }
        
        // get Member's current Location
        $result = $this->singleLookup(
            "
SELECT  members.IdCity
FROM    members
WHERE   members.id = $IdMember
            "
        );
        if (!isset($result) || $result->IdCity != $geonameid) {
            // Check Geo and maybe add location 
            $geomodel = new GeoModel(); 
    		if(!$geomodel->getDataById($geonameid)) {
                // if the geonameid is not in our DB, let's add it
                if (!$geomodel->addGeonameId($geonameid,'member_primary')) {
        		    $vars['errors'] = array('geoinserterror');
                    return false;
                }
            } else {
                // the geonameid is in our DB, so just update the counters
                //get id for usagetype:
        		$usagetypeId = $geomodel->getUsagetypeId('member_primary')->id;
                $update = $geomodel->updateUsageCounter($geonameid,$usagetypeId,'add');
            }
            
            $result = $this->singleLookup(
                "
UPDATE  addresses
SET     IdCity = $geonameid
WHERE   IdMember = $IdMember
                "
            );
            
            // name is not set:
            if (!empty($result)) $errors['Geonameid'] = 'Geoname not set';
            
            $result = $this->singleLookup(
                "
UPDATE  members
SET     IdCity = $geonameid
WHERE   id = $IdMember
                "
            );
            if (!empty($result)) $errors['Geonameid'] = 'Member IdCity not set';
            else MOD_log::get()->write("The Member with the Id: ".$IdMember." changed his location to Geo-Id: ".$geonameid, "Members");
            return array(
                'errors' => $errors,
                'IdMember' => $result
                );
        } else {
            // geonameid hasn't changed
            return false;
        }
    }
    
    
    /**
     * Not totally sure it belongs here - but better this
     * than member object? As it's more of a business of this
     * model to know about different states of the member 
     * object to be displayed..
     */
    public function set_profile_language($langcode)
    {
        //TODO: check that 
        //1) this is a language recognized by the bw system
        //2) there's content for this member in this language
        //else: use english = the default already set
        $langcode = mysql_real_escape_string($langcode);
        if ($language = $this->singleLookup(
            "
SELECT SQL_CACHE
    id,
    ShortCode
FROM
    languages
WHERE
    shortcode = '$langcode'
            "
        )) {
            $this->profile_language = $language;
        } else {
            $l = new stdClass;
            $l->id = 0;
            $l->ShortCode = 'en';
            $this->profile_language = $l;
        }
    }
    
    
    public function get_profile_language()
    {
        if(isset($this->profile_language)) {
            return $this->profile_language;
        } else {
            $l = new stdClass;
            $l->id = 0;
            $l->ShortCode = 'en';
            $this->profile_language = $l;
            // TODO: Next line deactivated to send "setlocation page" online, repair when member-app goes online
            // echo "l:";
            return $this->profile_language;
        }
    }

    // checkCommentForm - NOT FINISHED YET !
    public function checkCommentForm(&$vars)
    {
        $errors = array();
        
        // geonameid
        if (empty($vars['geonameid']) || empty($vars['countryname'])) {
            $errors[] = 'SignupErrorProvideLocation';
        }
        
        $TCom = $this->get_comments_commenter($_SESSION['IdMember']);
        
        // Mark if an admin's check is needed for this comment (in case it is "bad")
		$AdminAction = "NothingNeeded";
		if ($Quality == "Bad") {
			$AdminAction = "AdminCommentMustCheck";
		}
		if (!isset ($TCom->id)) {
			$str = "insert into comments(IdToMember,IdFromMember,Lenght,Quality,TextWhere,TextFree,AdminAction,created) values (" . $IdMember . "," . $_SESSION['IdMember'] . ",'" . $LenghtComments . "','" . $Quality . "','" . $TextWhere . "','" . $TextFree . "','" . $AdminAction . "',now())";
			$qry = sql_query($str) or bw_error($str);
		    $TCom->id = mysql_insert_id() ;
		} else {
			$str = "update comments set AdminAction='" . $AdminAction . "',IdToMember=" . $IdMember . ",IdFromMember=" . $_SESSION['IdMember'] . ",Lenght='" . $LenghtComments . "',Quality='" . $Quality . "',TextFree='" . $TextFree . "' where id=" . $TCom->id;
			$qry = sql_query($str) or bw_error($str);
		}
    }
    
    
    /**
     * Check form values of Mandatory form,
     * should always be analog to /build/signup/signup.model.php !!
     *
     * @param unknown_type $vars
     * @return unknown
     */
	public function checkUpdateMandatoryForm(&$vars)
    {
        $errors = array();

        // geonameid
        if (empty($vars['geonameid']) || empty($vars['countryname'])) {
            $errors[] = 'SignupErrorProvideLocation';
            unset($vars['geonameid']);
        }
            
        // housenumber
        if (!isset($vars['housenumber']) || 
            !preg_match(self::HANDLE_PREGEXP_HOUSENUMBER, $vars['housenumber'])) {
            $errors[] = 'SignupErrorProvideHouseNumber';
        }
        
        // street
        if (empty($vars['street']) || 
            !preg_match(self::HANDLE_PREGEXP_STREET, $vars['street'])) {
            $errors[] = 'SignupErrorProvideStreetName';
        }
        
        // zip
        if (!isset($vars['zip'])) {
            $errors[] = 'SignupErrorProvideZip';
        }
        
        // username
        if (!isset($vars['username']) || 
                !preg_match(self::HANDLE_PREGEXP, $vars['username']) ||
                strpos($vars['username'], 'xn--') !== false) {
            $errors[] = 'SignupErrorWrongUsername';
        } elseif (MOD_member::getMember_username($vars['username']) != 0) {
            $errors[] = 'SignupErrorUsernameAlreadyTaken';
        }
        
        // email (e-mail duplicates in BW database allowed)
        if (!isset($vars['email']) || !PFunctions::isEmailAddress($vars['email'])) {
            $errors[] = 'SignupErrorInvalidEmail';
        }
        
        // password
        if (!isset($vars['password']) || !isset($vars['passwordcheck']) ||
                strlen($vars['password']) < 6 || 
                strcmp($vars['password'], $vars['passwordcheck']) != 0
        ) {
            $errors[] = 'SignupErrorPasswordCheck';
        }
        
        // firstname, lastname
        if (empty($vars['firstname']) || !preg_match(self::HANDLE_PREGEXP_FIRSTNAME, $vars['firstname']) ||
            empty($vars['lastname']) || !preg_match(self::HANDLE_PREGEXP_LASTNAME, $vars['lastname'])
        ) {
            $errors[] = 'SignupErrorFullNameRequired';
        }
             
        // (skipped:) secondname

        // gender
        if (empty($vars['gender']) || ($vars['gender']!='female' && $vars['gender']!='male')) {
            $errors[] = 'SignupErrorProvideGender';
        }
        
        // birthyear
        $birthmonth = 12;
        if (!empty($vars['birthmonth'])) {
            $birthmonth = $vars['birthmonth'];
        }
        $birthday = 28;    // TODO: could sometimes be 29, 30, 31
        if (!empty($vars['birthday'])) {
            $birthday = $vars['birthday'];
        }
        if (empty($vars['birthyear']) || !checkdate($birthmonth, $birthday, $vars['birthyear'])) {
            $errors[] = 'SignupErrorBirthDate';
        } else {
            $vars['iso_date'] =  $vars['birthyear'] . "-" . $birthmonth . "-" . $birthday;
            if ($this->ageValue($vars['iso_date']) < self::YOUNGEST_MEMBER) {
                $errors[] = 'SignupErrorBirthDateToLow';
            }
        }
        
        // (skipped:) birthmonth

        // (skipped:) birthday

        // (skipped:) age hidden

        // terms
        if (empty($vars['terms']) || !$vars['terms']) {
            $errors[] = 'SignupMustacceptTerms';    // TODO: looks like a wrong case in "Accept"
        }
        
        return $errors;
    }


}


?>