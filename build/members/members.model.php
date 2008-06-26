<?php


class MembersModel extends RoxModelBase
{
    
    private $profile_language = null;
    
    public function getMemberWithUsername($username)
    {
        //echo "<pre>model.getMemberWithUsername $username";
        $values = $this->singleLookup_assoc(
            "
SELECT *
FROM members
WHERE Username = '$username'
            "
        );
        //return new Member($values, $this->dao);
        if($values) {
            //print_r($values);
            return new Member($values, $this->dao);
        }
           else {
               echo "No member found with username $username";
           }
        
    }
    
    public function getMemberWithId($id)
    {
        $id = (int)$id;
        echo "model.getMemberWithId $id";        
        $values = $this->singleLookup_assoc(
            "
SELECT *
FROM members
WHERE id = $id
            "
        );
        if($values) {
            print_r($values);
            return new Member($values, $this->dao);
        } else {
            //print_r($values);
            echo "No member found with id $id";
            return null;
        }
    }
    
    
    /**
     * Not totally sure it belongs here - but better this
     * than member object? As it's more of a business of this
     * model to know about different states of the member 
     * object to be displayed..
     */
    public function set_profile_language($language) {
        //TODO: check that 
        //1) this is a language recognized by the bw system
        //2) there's content for this member in this language
        //else: use english = the default already set
        
        $language = $this->singleLookup("
SELECT SQL_CACHE id, ShortCode
FROM languages
WHERE shortcode = '$language'
                ");

        if ($language != null) {
            $this->profile_language = $language;
        }
        else {
            $l = new stdClass;
            $l->id = 0;
            $l->ShortCode = 'en';
            $this->profile_language = $l;
        }
    }
    
    
    public function get_profile_language() {
        if(isset($this->profile_language)) {
            return $this->profile_language;
        }
        else {
            $l = new stdClass;
            $l->id = 0;
            $l->ShortCode = 'en';
            $this->profile_language = $l;
            echo "l:";
            return $this->profile_language;
        }
    }   
}




class Member extends RoxEntityBase
{
    private $trads = null;
    private $trads_by_tradid = null;
    private $address = null;
    private $profile_languages = null;
    
    public function construct($values, $dao)
    {
        parent::__construct($values, $dao);
    }
    
    
    /**
     * Checks which languages profile has been translated into
     */
    public function get_profile_languages() {
        if(!isset($this->trads)) {
            $this->trads = $this->get_trads();
        }
        return $this->profile_languages;
    }
    
    
    /**
     * automatically called by __get('trads'),
     * when someone writes '$member->trads'
     *
     * @return unknown
     */
    protected function get_trads()
    {
        $trads_for_member = $this->bulkLookup(
            "
SELECT SQL_CACHE *
FROM memberstrads
WHERE IdOwner = $this->id 
            "
        );
        
        $language_data = $this->bulkLookup(
            "
SELECT SQL_CACHE id, ShortCode
FROM languages 
            ", 
            "id"
        );        
        $trads_by_tradid = array();
        $this->profile_languages = array();
        foreach ($trads_for_member as $trad) {
            if (!isset($trads_by_tradid[$trad->IdTrad])) {
                $trads_by_tradid[$trad->IdTrad] = array();
            }
            $trads_by_tradid[$trad->IdTrad][$trad->IdLanguage] = $trad;
            //keeping track of which translations of the profile texts have been encountered
            $language_id = $trad->IdLanguage;
            $this->profile_languages[$language_id] = $language_data[$language_id]->ShortCode;
        }
        $this->trads_by_tradid= $trads_by_tradid;
        
        $field_names = array(
            'ILiveWith',
            'MaxLenghtOfStay',
            'MotivationForHospitality',
            'Offer',
            'Organizations',
            'AdditionalAccomodationInfo',
            'InformationToGuest',
            'Hobbies',
            'Books',
            'Music',
            'Movies',
            'PleaseBring',
            'OfferGuests',
            'OfferHosts',
            'PublicTransport',
            'PastTrips',
            'PlannedTrips',
            'ProfileSummary'
        );
        
        $trads_by_fieldname = new stdClass();
        foreach ($field_names as $name) {
            if (!$trad_id = $this->$name) {
                // whatever
            } else if (!isset($trads_by_tradid[$trad_id])) {
                $trads_by_fieldname->$name = array();
            } else {
                $trads_by_fieldname->$name = $trads_by_tradid[$trad_id];
            }
        }
        return $trads_by_fieldname;
    }
    
    

  
    
    /**
     * TODO: get name from crypted fields in an architecturally sane place (to be determined)
     */    
    public function get_name() {
        $name1 = $this->get_crypted($this->FirstName, "*");
        $name2 = $this->get_crypted($this->SecondName, "*");
        $name3 = $this->get_crypted($this->LastName, "*");
        $name = $name1." " . $name2 . " " . $name3;
        return $name;
    }
    
    
    public function get_messengers() {
          $messengers = array(
            array("network" => "GOOGLE", "nicename" => "Google Talk", "image" => "icon_gtalk.png"), 
            array("network" => "ICQ", "nicename" => "ICQ", "image" => "icon_icq.jpg"), 
            array("network" => "AOL", "nicename" => "AOL", "image" => "icon_aim.png"), 
            array("network" => "MSN", "nicename" => "MSN", "image" => "icon_msn.png"), 
            array("network" => "YAHOO", "nicename" => "Yahoo", "image" => "icon_yahoo.png"), 
            array("network" => "SKYPE", "nicename" => "Skype", "image" => "icon_skype.png")
        );
          $r = array();
          foreach($messengers as $m) {
              $address_id = $this->__get("chat_".$m['network']);
              $address = $this->get_crypted($address_id, "*");
              if(isset($address) && $address != "*") {
                  $r[] = array("network" => $m["nicename"], "image" => $m["image"], "address" => $address);
              }
          }
          if(sizeof($r) == 0)
              return null;
          return $r;
    }
    
    
    public function get_age() {
        $age = $this->get_crypted("age", "hidden");
        return $age;
    }

    
    public function get_street() {
        if(!isset($this->address)) {
            $this->get_address();
        }
        return $this->get_crypted($this->address->StreetName, '* member doesn\'t want to display');
    }
    

    public function get_zip() {
        if(!isset($this->address)) {
            $this->get_address();
        }
        return $this->get_crypted($this->address->Zip, '* Zip is hidden in '.$this->address->CityName);        
    }


    public function get_city() {
        if(!isset($this->address)) {
            $this->get_address();
        }
        return $this->address->CityName;
    }
    
    
    public function get_region() {
        //echo "address: " . $this->address;
        if(!isset($this->address)) {
            $this->get_address();
        }        
        //echo "address: " . $this->address;
        return $this->address->RegionName;
    }


    public function get_country() {
        //echo "address: " + $this->address;
        //return "" 
        
        if(!isset($this->address)) {
            //echo "No address set, getting it!";
            $this->get_address();
        }        
        $r = $this->address->CountryName;
        //echo "r: " + $r;
        return $r;
    }
    

    public function get_countrycode() {
        //echo "address: " + $this->address;
        if(!isset($this->address)) {
            $this->get_address();
        }        
        return $this->address->CountryCode;
    }


    
    public function get_photo() {
        $photos = $this->bulkLookup(
            "
SELECT * FROM membersphotos        
WHERE IdMember = ".$this->id    
        );
        
        return $photos;
    }
    
    
    
    public function get_previous_photo($photorank) {
        $photorank--;
        
        if($photorank < 0) {
            $photos = $this->bulkLookup(
                "
SELECT * FROM membersphotos        
WHERE IdMember = $this->id
ORDER BY SortOrder DESC LIMIT 1"    
            );
        }
        
    }
/*
$photorank=GetParam("photorank",0);
switch (GetParam("action")) {
    case "previouspicture" :
        $photorank--;
        if ($photorank < 0) {
              $rr=LoadRow("select SQL_CACHE * from membersphotos where IdMember=" . $IdMember . " order by SortOrder desc limit 1");
            if (isset($rr->SortOrder)) $photorank = $rr->SortOrder;
            else $photorank=0;
        }
        break;
    case "nextpicture" :
        $photorank++;
        break;
    case "logout" :
        Logout();
        exit (0);
}
 */    
    public function count_comments() 
    {
        $positive = $this->bulkLookup(
            "
SELECT COUNT(*) AS positive
FROM comments 
WHERE IdToMember = ".$this->id."
AND Quality = 'Good'
             "
         );

        $all = $this->bulkLookup(
            "
SELECT COUNT(*) AS sum
FROM comments 
WHERE IdToMember = ".$this->id
         );
         
         $r = array('positive' => $positive[0]->positive, 'all' => $all[0]->sum);
         return $r;
    }
    
    
    /**
     * automatically called by __get('group_memberships'),
     * when someone writes '$member->group_memberships'
     *
     * @return unknown
     */
    public function get_group_memberships()
    {
        $groups_for_member = $this->bulkLookup(
            "
SELECT SQL_CACHE
    membersgroups.*,
    groups.*
FROM
    membersgroups,
    groups
WHERE
    membersgroups.IdMember = $this->id  AND
    membersgroups.IdGroup = groups.id
            "
        );
        return $groups_for_member;
    }
    
    
   
    /**
     * Member address lookup
     */
    protected function get_address() {
        $sql =
           "
SELECT
    SQL_CACHE a.*,
    ci.Name      AS CityName,
    r.Name       AS RegionName,
    co.Name      AS CountryName,
    co.isoalpha2 AS CountryCode
FROM
    addresses    AS a,
    cities       AS ci,
    regions      AS r,
    countries    AS co
WHERE
    a.IdMember  = $this->id  AND
    a.IdCity    = ci.id      AND
    ci.IdRegion = r.id       AND
    r.IdCountry = co.id
            "
        ;
        $a = $this->bulkLookup($sql);
        if($a != null && sizeof($a) > 0) {
            $this->address = $a[0];
        }            
    }
    
        
      public function get_relations() {
          $sql = " 
SELECT
    members.Username
FROM
    specialrelations,
    members          
WHERE
    specialrelations.IdOwner = $this->id  AND
    specialrelations.IdRelation = members.Id                  
          ";
          return $this->bulkLookup($sql);
      }
  
  
      public function get_visitors() {
          $sql = " 
SELECT
    members.Username
FROM
    profilesvisits,
    members          
WHERE
    profilesvisits.IdMember  = $this->id  AND
    profilesvisits.IdVisitor = members.Id                  
          ";
          return $this->bulkLookup($sql);
      }
      
      
      
      public function get_comments() {
          $sql = " 
SELECT *
FROM
    comments,
    members          
WHERE
    comments.IdToMember   = $this->id  AND
    comments.IdFromMember = members.Id                  
          ";
          
          //echo $sql;
          //print_r($r);
          return $this->bulkLookup($sql);
          
      }
      
        
    /**
     * Fetches translation of specific field in user profile. 
     * Initializes instance variable $trads if it hasn't been 
     * initialized already.
     * 
     * @param fieldname name of the profile field
     * @param language required translation 
     * 
     * @return text of $fieldname if available, English otherwise, 
     *     and empty string if field has no content
     */
    public function get_trad($fieldname, $language) {
        if(!isset($this->trads)) {
            $this->trads = $this->get_trads();
        }
        
        if(!isset($this->trads->$fieldname)) 
            return "";
        else {
            $field = $this->trads->$fieldname;
            if(!array_key_exists($language, $field)) {
                //echo "Not translated";
                if($language != 0)
                    return $field[0]->Sentence;
                else return "";
            }
            else {
                return $field[$language]->Sentence;
            }
        }
    }
    
    
    public function get_trad_by_tradid($tradid, $language) {
        if(!isset($this->trads)) {
            $this->get_trads();
        }    
        
        if(!isset($this->trads_by_tradid[$tradid])) 
            return "";
        else {
            $trad = $this->trads_by_tradid[$tradid];
            if(!array_key_exists($language, $trad)) {
                //echo "Not translated";
                if($language != 0)
                    return $trad[0]->Sentence;
                else return "";
            }
            else {
                return $trad[$language]->Sentence;
            }
        }            
    }
            
                
    /**
     * This needs to go someplace else, 
     * pending architectural attention
     */
    protected function get_crypted($crypted_id, $return_value)
    {
        $crypted_id = (int)$crypted_id;
        $rr = $this->bulkLookup(
            "
SELECT * 
FROM cryptedfields
WHERE id = $crypted_id
            "
        );
        
        if ($rr != NULL && sizeof($rr) > 0)
        {
            $rr = $rr[0];
            if ($rr->IsCrypted == "not crypted") {
                return $rr->MemberCryptedValue;
            }
            if ($rr->MemberCryptedValue == "" || $rr->MemberCryptedValue == 0) {
                return (""); // if empty no need to send crypted
            }
            if ($rr->IsCrypted == "crypted") {
                return ($return_value);
            }            
        }    
        /*elseif(sizeof($rr) > 0) {
            return ("");
        }*/
        else { 
            return ($return_value);
        }
    }
    
    
    /**
     * Should fetch male & female dummy pics when the member doesn't
     * have any photos uploaded. membersphotos.id for those images = ??
     */
    public function getProfilePictureID() {
        $q = "
SELECT id FROM membersphotos WHERE IdMember = ".$this->id. " ORDER BY SortOrder ASC LIMIT 1 
        		";
        $id = $this->singleLookup_assoc($q);
        if($id) {
        	return $id['id'];
        }
        return null;        		
    }
}


/**
 * TODO: is this class actually used?
 * if group membership does not have any interactivity,
 * then it will be easier to just use an stdClass instead.
 * 
 * To keep in mind:
 * It will be a good idea not to let instances of GroupMembership make SQL queries.
 * We will have a lot of instances of GroupMembership per member,
 * and if each of them makes a query, it would be far too much. 
 * Better to look up the shit all at once.
 */
class GroupMembership extends RoxEntityBase
{
    public function construct($values, $dao)
    {
        parent::__construct($values, $dao);
    }
}


?>