<?php
/**
 * Members polls
 * 
 * @package about polls
 * @author jeanyves
 * @copyright Copyright (c) 2005-2006, myTravelbook Team
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL)
 * @version $Id$
 */
class PollsModel extends RoxModelBase {

    /**
     * this function returns true if the user can contribute to this poll, false elsewher
		 * @IdPoll is the id of the poll
		 * @$Email is the mandatory Email which must be provided for a not logged user
     **/
    function CanUserContribute($IdPoll,$Email="") { 
			$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll." /* can user contribute */") ;
			
// Check that the poll is open
			if ($rPoll->Status!="Open") {
//      	MOD_log::get()->write("CanUserContribute in a closed poll","polls") ; 				
				return(false) ;
			}
// Check that we are is the range time people can contribute
	  	 if (time()<strtotime($rPoll->Started)) {
//      	 MOD_log::get()->write("CanUserContribute in a not started poll time()=".time()." strtotime('".$rPoll->Started."')=".$rPoll->Started,"polls") ; 				
			 	 return(false) ;
			 }
	  	 if ((time()>strtotime($rPoll->Ended)) and ($rPoll->Ended!="0000-00-00 00:00:00")) {
//			 echo " time()=",time()," strtotime(\$rPoll->Ended)=",strtotime($rPoll->Ended)," ",$rPoll->Ended ;
//      	 MOD_log::get()->write("CanUserContribute in an already ended poll","polls") ; 				
			 	 return(false) ;
			 }

// If it is a memberonly poll check that the member is logged in  
			if ($rPoll->ForMembersOnly=="Yes") {
				if ((!isset($_SESSION["IdMember"])) or ($_SESSION["MemberStatus"]!="Active")) {
//      	  MOD_log::get()->write("trying to vote in an member only post and not logged in","polls") ; 				
					return (false) ;
				}
			}
			else { // case not for member only, and Email must be provided
				if (empty($Email)) {
      	  MOD_log::get()->write("CanUserContribute in without being logged but without email","polls") ; 				
					return(false) ;
				}
				if (($rPoll->CanChangeVote=='No') and ($this->HasAlreadyContributed($IdPoll,$Email))) {
//      	  MOD_log::get()->write("CanUserContribute in an already contributed post with Email".$Email,"polls") ; 				
					return(false) ;
				}
			}
			if (($rPoll->CanChangeVote=='No') and ($this->HasAlreadyContributed($IdPoll,"",$_SESSION["IdMember"]))) {
//      	  MOD_log::get()->write("CanUserContribute in an already contributed post ","polls") ; 				
					return(false) ;
			}
			return(true) ;
		} // end of CanUserContribute

		

    /**
     * this function returns false if the result of the poll are not available
		 * @IdPoll is the id of the poll
     **/
    function GetPollResults($IdPoll,$Email="",$IdMember=0) {
			$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll." /* GetPollResults */") ;
			if ($rPoll->ResultsVisibility=='Not Visible') {
				return(false) ;
			}
			// Todo: proceed with status VisibleAfterVisit in a better way (for now someone who knows the results of a not closed poll)
			
			$Data->rPoll=$rPoll ;
			$rr=$this->singlelookup("select count(*) as TotContrib from polls_contributions where  IdPoll=".$IdPoll) ;
			$TotContrib=$Data->TotContrib=$rr->TotContrib ;
			$Data->Choices=$this->bulkLookup("select *,(Counter/".$TotContrib.")*100 as Percent from polls_choices where IdPoll=".$IdPoll." order by Counter,created desc") ;
			$Data->Contributions=$this->bulkLookup("select comment,Username from polls_contributions,members where  IdPoll=".$IdPoll." and comment <>'' and members.id=polls_contributions.IdMember") ;
	
			return($Data) ;
			
	  } // end of GetPollResults

    /**
     * this function returns true if the user has allready contributed to the specific post
		 * @IdPoll is the id of the poll
		 * @$Email is the mandatory Email which must be provided for a not logged user (optional)
		 * @$IdMember id of the member (optional)
     **/
    function HasAlreadyContributed($IdPoll,$Email="",$IdMember=0) {
			if (!empty($IdMember)) {
				$rr=$this->singleLookup("select count(*) as cnt from polls_contributions where IdMember=".$IdMember." and IdPoll=".$IdPoll) ;
				if ($rr->cnt>0) return(true) ;  
			}
			if (!empty($Email)) {
				$rr=$this->singleLookup("select count(*) as cnt from polls_contributions where Email='".$Email." and IdPoll=".$IdPoll) ;
				if ($rr->cnt>0) return(true) ;  
			}
			return(false) ;
		} // end of HasAlreadyContributed

    /**
     * this function allows to create poll
		 * @post is the array of the arg_post to be given by the controller
		 * returns true if the poll is added with success
		 * according to $post['IdPoll'] teh poll will be inserted or updated
     **/
    function UpdatePoll($post) {
			$words = new MOD_words();
		  if (empty($post['IdPoll'])) {
				$IdPoll=0 ;
			}
			else {
				$IdPoll=$post['IdPoll'] ;
			}
			if ($IdPoll==0) { // IF it is a new poll
				$IdGroup=0 ;
				

				$ss="insert into polls(IdCreator,IdGroupCreator,created,Title,Description,Status,AllowComment,ResultsVisibility,Started) " ;
				$ss=$ss."values(".$_SESSION["IdMember"].",0,now(),0,0," ;
				$ss=$ss."'Project','No','Not Visible',now())" ;
 				$result = $this->dao->query($ss);
				if (!$result) {
	   			throw new PException('UpdatePoll::Failed to insert a poll ');
				}
				$IdPoll=$result->insertId();

				$ss=$this->dao->escape($post['Title']) ;
				$IdTitle=$words->InsertInFTrad($ss,"polls.Title",$IdPoll, $_SESSION["IdMember"], 0) ;

				$ss=$this->dao->escape($post['Description']) ;
				$IdDescription=$words->InsertInFTrad($ss,"polls.Description",$IdPoll, $_SESSION["IdMember"], 0) ;
				
				$ss="update polls set Title=$IdTitle,Description=$IdDescription where id=$IdPoll" ;
 				$result = $this->dao->query($ss);
				if (!$result) {
	   			throw new PException('UpdatePoll::Failed to add back the Title and Description ');
				}

				MOD_log::get()->write("poll : ".$post["Title"]." created IdPoll=#".$IdPoll,"polls") ; 
			} // end if it is a new poll
			else {
				$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll) ;
			if (!( (isset($_SESSION["IdMember"]) and ($rPoll->IdCreator==$_SESSION["IdMember"]) and ($rPoll->Status=="Projet")) or (MOD_right::get()->HasRight("Poll","update")) )) {
			MOD_log::get()->write("Forbidden to update  poll #".$IdPoll ,"polls") ; 
					die ("Sorry forbidden for you") ;
			}
			
			$IdCreator=$rPoll->IdCreator ;
				if (isset($post['CreatorUsername'])) {	
					$rr=$this->singleLookup("select id from members where Username='".$post['CreatorUsername']."' and Status='Active'") ;
					if (isset($rr->id)) {
						$IdCreator=$rr->id ;
					}
				}
				
				
				if (($post["Status"]=="Open")and ($rPoll->Started=="0000-00-00 00:00:00")) {
					$Started="now()" ;
					MOD_log::get()->write("Starting Id Poll#".$IdPoll." for the first time","polls") ; 
				}
				else {
					$Started="'".$rPoll->Started."'" ;
				}
				
				$ss="update polls set IdCreator=".$IdCreator.",IdGroupCreator=".$rPoll->IdGroupCreator ;
				$ss=$ss.",Status='".$post["Status"]."'" ;
				$ss=$ss.",TypeOfChoice='".$post["TypeOfChoice"]."'" ;
				$ss=$ss.",Started=".$Started ;
				$ss=$ss.",Ended='".$post["Ended"]."'" ;
				$ss=$ss.",ResultsVisibility='".$post["ResultsVisibility"]."'" ;
				$ss=$ss.",AllowComment='".$post["AllowComment"]."'" ;
				$ss=$ss.",Anonym='".$post["Anonym"]."'" ;
				$ss=$ss.",Ended='".$post["Ended"]."'" ;
				$ss=$ss." where id=".$IdPoll;
 				$result = $this->dao->query($ss);


				$ss=$this->dao->escape($post['Description']) ;
		 		$words->ReplaceInFTrad($ss,"polls.Description",$rPoll->id,$rPoll->Description) ;
				
				$ss=$this->dao->escape($post['Title']) ;
		 		$words->ReplaceInFTrad($ss,"polls.Title",$rPoll->id,$rPoll->Title) ;

				MOD_log::get()->write("poll : ".$post["Title"]." updating IdPoll=#".$IdPoll." Set Status=".$rPoll->Status." to ".$post["Status"],"polls") ; 
			}
		} // end of UpdatePoll

    /**
     * this function allows to add a choice to a give poll
		 * @post all parameters from  argpost
		 * returns true if choice was added with success
     **/
    function AddChoice($post) {
			$words = new MOD_words();

			$IdPoll=$post['IdPoll'] ;
			$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll." /* Add Choice */") ;
			
			if (!( (isset($_SESSION["IdMember"]) and ($rPoll->IdCreator==$_SESSION["IdMember"]) and ($rPoll->Status=="Projet")) or (MOD_right::get()->HasRight("Poll","update")) )) {
			MOD_log::get()->write("Forbidden to add poll choice for poll #".$IdPoll ,"polls") ; 
					die ("Sorry forbidden for you") ;
			}
			

			$ss="insert into polls_choices(IdPoll,IdChoiceText,Counter,created) values(".$rPoll->id.",0,0,now())" ;
 			$result = $this->dao->query($ss);
			if (!$result) {
	   		throw new PException('AddChoice::Failed to add back the insert the choice ');
			}
			$IdChoice=$result->insertId();

			$ss=$this->dao->escape($post['ChoiceText']) ;
			$IdChoiceText=$words->InsertInFTrad($ss,"polls_choices.IdChoiceText",$IdChoice, $_SESSION["IdMember"], 0) ;
			
			$ss="update polls_choices set IdChoiceText=$IdChoiceText where id=$IdChoice" ;
 			$result = $this->dao->query($ss);
			if (!$result) {
	   		throw new PException('AddChoice::Failed update the IdChoiceText ');
			}

			MOD_log::get()->write("pollchoice : <b>".$post["ChoiceText"]."</b> created IdPollChoice=#".$IdChoice." for poll #".$IdPoll ,"polls") ; 
			
			return(true) ;
		} // end of AddChoice

    /**
     * this function allows to add a choice to a give poll
		 * @post all parameters from  argpost
		 * returns true if choice was added with success
     **/
    function UpdateChoice($post) {
			$words = new MOD_words();

			$IdPoll=$post['IdPoll'] ;
			$IdPollChoice=$post['IdPollChoice'] ;
			$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll." /* UpdatedChoice */") ;
			
			if (!( (isset($_SESSION["IdMember"]) and ($rPoll->IdCreator==$_SESSION["IdMember"]) and ($rPoll->Status=="Projet")) or (MOD_right::get()->HasRight("Poll","update")) )) {
			MOD_log::get()->write("Forbidden to update poll choice for poll #".$IdPoll ,"polls") ; 
					die ("Sorry forbidden for you") ;
			}
			
			
			$rPollChoice=$this->singleLookup("select * from polls_choices where id=".$IdPollChoice." /* UpdatedChoice*/") ;
			
			$ss=$this->dao->escape($post['ChoiceText']) ; 
		 	$words->ReplaceInFTrad($ss,"polls_choices.IdChoiceText",$rPollChoice->id,$rPollChoice->IdChoiceText) ;

			MOD_log::get()->write("pollchoice : update to <b>".$post["ChoiceText"]."</b> IdPollChoice=#".$IdPollChoice." for poll #".$IdPoll ,"polls") ; 
			
			return(true) ;
		} // end of UpdatedChoice

    /**
     * this function adds the vote for a given member
		 * @post is the array of the arg_post to be given by the controller
		 * @$Email is the mandatory Email which must be provided for a not logged user (optional)
		 * @$IdMember id of the member (optional)
		 * returns true if the vote is added with success
     **/
    function AddVote($post,$Email="",$IdMember=0) {

		if (empty($post['IdPoll'])) {
			die ("Fatal error In AddVote \$post['IdPoll'] is missing") ;
		}
		$IdPoll=$post['IdPoll'] ;
		$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll." /* Add Vote */") ;
		$rContribList=$this->bulkLookup("select * from polls_choices  where IdPoll=".$IdPoll) ;

		$wherefordelete="" ; // very important to avoid to delete all votes 
		if (!empty($IdMember)) {
			$wherefordelete="IdMember='".$IdMember."'" ;
		}
		if (!empty($Email)) {
			$wherefordelete="Email='".$Email."'" ;
		}
			
		if ($rPoll->TypeOfChoice=='Exclusive') {
			if (!empty($post['ExclusiveChoice'])) { // blank votes are allowed
				$Choice=$post['ExclusiveChoice'] ;
				$ss="update polls_choices set Counter=Counter+1 where id=".$Choice." and IdPoll=".$IdPoll ;
  				$s = $this->dao->query($ss);
   	 			if (!$s) {
      	   			throw new PException('Failed to add a vote ');
   	 			}
			}
			else {
				$Choice=0 ;
			}
					
			$ss="insert into polls_contributions(IdMember,Email,created,comment,IdPoll) values (".$IdMember.",'".$Email."',now(),'".$this->dao->escape($post['Comment'])."',".$IdPoll.")" ;
  			$s = $this->dao->query($ss);
   	 		if (!$s) {
				throw new PException('Failed to insert into polls_contributions ');
   	 		}
					
			if ($rPoll->Anonym=='No') {
				$ss="insert into polls_record_of_choices(IdMember,Email,created,IdPollChoice,IdPoll) values (".$IdMember.",'".$Email."',now(),".$Choice.",".$IdPoll.")" ;
  		 		$s = $this->dao->query($ss);
   	 			if (!$s) {
					throw new PException('Failed to insert into polls_record_of_choices ');
   	 			}
					
			}
					
      	MOD_log::get()->write("Vote Exclusive vote from poll #".$IdPoll." for IdMember=#".$IdMember." ".$Email,"polls") ;
		}
			
		if ($rPoll->TypeOfChoice=='Inclusive') {
			$ss="insert into polls_contributions(IdMember,Email,created,comment,IdPoll) values (".$IdMember.",'".$Email."',now(),'".$this->dao->escape($post['Comment'])."',".$IdPoll.")" ;
  		 	$s = $this->dao->query($ss);
			if (!$s) {
      		   throw new PException('Failed to insert into polls_contributions ');
   	 		}
			for ($ii=0;$ii<count($rContribList);$ii++) {
				$rContrib=$rContribList[$ii] ;
//				echo "\$post[\"choice_".$rContrib->id."\"]=",$post["choice_".$rContrib->id],"<br />" ;
				if ((isset($post["choice_".$rContrib->id])) and ($post["choice_".$rContrib->id]=='on')) { // if this choice was made
					$ss="update polls_choices set Counter=Counter+1 where id=".$rContrib->id ;
  		 			$s = $this->dao->query($ss);
					$Choice=$rContrib->id ;
   	 				if (!$s) {
						throw new PException('Failed to add a vote ');
   	 				}

					if ($rPoll->Anonym=='No') {
						$ss="insert into polls_record_of_choices(IdMember,Email,created,IdPollChoice,IdPoll) values (".$IdMember.",'".$Email."',now(),".$Choice.",".$IdPoll.")" ;
  		 				$s = $this->dao->query($ss);
						if (!$s) {
							throw new PException('Failed to insert into polls_record_of_choices ');
   	 					}
					}
				} // end if this choice was made

			}
			MOD_log::get()->write("add Inclusive vote from poll #".$IdPoll." for IdMember=#".$IdMember." ".$Email,"polls") ;
		}
		if ($rPoll->TypeOfChoice=='Ordered') {
			die("Add  in ordered votes not implemented") ;
		}
			
		return(true) ;
	} // end of AddVote
		
		
		 /**
     * this function cancels the vote for a given member
		 * @IdPoll is the id of the poll
		 * @$Email is the mandatory Email which must be provided for a not logged user (optional)
		 * @$IdMember id of the member (optional)
		 * returns true if the vote is cancelled
     **/
    function CancelVote($IdPoll,$Email="",$IdMember=0) {
			$rPoll=$this->singleLookup("select * from polls where id=".$IdPoll) ;
			if ($rPoll->Status=="Closed") {
      	  MOD_log::get()->write("Cannot cancel vote from poll #".$IdPoll." which is closed","polls") ;
					return(false) ; 				
			}
			if ($rPoll->CanChangeVote=="No") {
      	  MOD_log::get()->write("Cannot cancel vote from poll #".$IdPoll." which doesn't allow to change vote","polls") ;
					return(false) ; 				
			}
			$rContrib=array() ;
			
			$wherefordelete=" (false==true) " ; // very important to avoid to delete all votes 
			if (!empty($IdMember)) {
				$rr=$this->singleLookup("select * from polls_contributions where IdPoll=".$IdPoll) ;
				if (!isset($rr->id)) return(false) ;  
				$wherefordelete="IdMember='".$IdMember."'" ;
			}
			elseif (!empty($Email)) {
				$rr=$this->singleLookup("select * from polls_contributions where Email='".$Email."'") ;
				if (!isset($rr->id)) return(false) ;  
				$wherefordelete="Email='".$Email."'" ;
			}

			$rContrib=$this->bulkLookup("select * from polls_record_of_choices  where IdPoll=".$IdPoll." and ".$wherefordelete) ;
			
			if ($rPoll->TypeOfChoice=='Exclusive') {
				for ($ii=0;$ii<count($rContrib);$ii++) { // In fact we should have just one record here 
					$ss="update polls_choices set Counter=Counter-1 where id=".$rContrib[$ii]->IdPollChoice ;
  		 		$s = $this->dao->query($ss);
   	 			if (!$s) {
      		   throw new PException('Failed to delete a vote ');
   	 			}
				}
				$ss="delete from polls_contributions where IdPoll=".$IdPoll." and ".$wherefordelete ;
  		 	$s = $this->dao->query($ss);
   	 		if (!$s) {
      		   throw new PException('Failed to delete a vote (contribution)');
   	 		}

				$ss="delete from polls_record_of_choices where IdPollChoice=".$IdPoll." and ".$wherefordelete ;
  		 	$s = $this->dao->query($ss);
   	 		if (!$s) {
      		   throw new PException('Failed to delete a vote (polls_record_of_choices)');
   	 		}
      	MOD_log::get()->write("Cancelling Exclusive vote from poll #".$IdPoll." for IdMember=#".$IdMember." ".$Email,"polls") ;

			}
			if ($rPoll->TypeOfChoice=='Inclusive') {
				for ($ii=0;$ii<count($rContrib);$ii++) {
					$ss="update polls_choices set Counter=Counter-1 where id=".$rContrib[$ii]->IdPollChoice ;
  		 		$s = $this->dao->query($ss);
   	 			if (!$s) {
      		   throw new PException('Failed to delete a vote ');
   	 			}

				}
				$ss="delete from polls_contributions where IdPoll=".$IdPoll." and ".$wherefordelete ;
  		 	$s = $this->dao->query($ss);
   	 		if (!$s) {
      		   throw new PException('Failed to delete a vote (contribution)');
   	 		}

				$ss="delete from polls_record_of_choices where IdPollChoice=".$IdPoll." and ".$wherefordelete;
  		 	$s = $this->dao->query($ss);
   	 		if (!$s) {
      		   throw new PException('Failed to delete a vote (polls_record_of_choices)');
   	 		}
      	MOD_log::get()->write("Cancelling Inclusive vote from poll #".$IdPoll." for IdMember=#".$IdMember." ".$Email,"polls") ;
			}
			if ($rPoll->TypeOfChoice=='Ordered') {
				die("Delete of ordered votes not implemented") ;
			}
			
			return(true) ;
		} // end of CancelVote

    /**
     * this function prepares the contribution for a poll
		 * @IdPoll is the id of the poll
     **/
    function PrepareContribute($IdPoll=0) {
		$Data->rPoll=$this->singleLookup("select * from polls where id=".$IdPoll) ;
		$choices_alreadydone=array() ;
		if ($this->HasAlreadyContributed($IdPoll)) {
			if ($Data->rPoll->Anonym=="No") {
				$ss="select * from polls_record_of_choices where IdMember=".$_SESSION["IdMember"]." and IdPoll=".$IdPoll ;
				$choices_alreadydone=$this->bulkLookup($ss) ;
			}
		}
		$Data->choices_alreadydone=$choices_alreadydone ;
		$Data->Choices=$this->bulkLookup("select * from polls_choices where IdPoll=".$IdPoll) ;
		return($Data) ;
	} // end of PrepareContribute

    /**
     * this function loads the data of a poll
		 * @IdPoll is the id of the poll
     **/
    function LoadPoll($IdPoll=0) {
			$ss="select polls.*,members.Username as 'CreatorUsername' from (polls)" ;
			$ss.=" left join members on members.id=polls.IdCreator " ;
			$ss=$ss. " where polls.id=".$IdPoll ;
			$Data->rPoll=$this->singleLookup($ss) ;
			$Data->Choices=$this->bulkLookup("select * from polls_choices where IdPoll=".$IdPoll." order by created asc") ;
			return($Data) ;
		} // end of LoadPoll

    /**
     * this function load the list of the polls with a certain status
		 * @PollStatus is the statuis which allow to filter for the status of some poll
     **/
    function LoadList($PollStatus="") {
				$words = new MOD_words();
				if (empty($PollStatus)) {
					$where="" ;
				} 
				else {
					$where = " where polls.Status='".$PollStatus."'";
				}
				
        
        $sQuery="select polls.*,members.Username as 'CreatorUsername' from (polls) " ;
				$sQuery.=" left join members on members.id=polls.IdCreator " ;
				$sQuery.=" left join groups on groups.id=polls.IdGroupCreator " ;
				$sQuery=$sQuery.$where." order by polls.created desc" ;
				$tt=array() ;
      	$qry = $this->dao->query($sQuery);
      	if (!$qry) {
            throw new PException('polls::LLoadList Could not retrieve the polls!');
      	}

				// for all the records
      	while ($rr = $qry->fetch(PDB::FETCH_OBJ)) {
					if (!empty($rr->IdGroupCreator)) { // In case the polls is created by a group find back the name of this group
						$rGroup=$this->singleLookup("select * from groups where id=".$rr->IdGroupCreator) ;
						$rr->GroupCreatorName=$words->getFormatted("Group_" . $rGroup->Name);
					}
					$rContrib=$this->singleLookup("select count(*) as cnt from polls_contributions where IdPoll=".$rr->id) ;
					$rr->NbContributors=$rContrib->cnt ;
					
// This is the logic for the possible action (may be this could be better in the controller)
					$rr->PossibleActions="" ;
					
					// Only owner of admin with proper right can update the poll
					if ( (isset($_SESSION["IdMember"]) and ($rr->IdCreator==$_SESSION["IdMember"]) and ($rr->Status=="Projet")) or (MOD_right::get()->HasRight("Poll","update")) ) {
						$rr->PossibleActions=$rr->PossibleActions."<a href=\"polls/update/".$rr->id."\">".$words->getFormatted("polls_adminlink")."</a>" ;
					}


					if ($this->HasAlreadyContributed($rr->id,"",$_SESSION["IdMember"])) {
						$rr->PossibleActions=$words->getFormatted("polls_youhavealreadyvoted") ;
						if ($rr->CanChangeVote=="Yes") {
						  $rr->PossibleActions.="<br /><a href=\"polls/cancelvote/".$rr->id."\">".$words->getFormatted("polls_remove_vote")."</a>" ;
						}
						if (($rr->ResultsVisibility=="VisibleAfterVisit") and ($rr->Status!="Closed")) {
						  $rr->PossibleActions=$rr->PossibleActions."<br /><a href=\"polls/seeresults/".$rr->id."\">".$words->getFormatted("polls_seeresults")."</a>" ;
						}
					}
					else {
						if ($rr->Status=="Closed") {
							$rr->PossibleActions="<a href=\"polls/results/".$rr->id."\">".$words->getFormatted("polls_seeresults")."</a>" ;
						}
					}
					if ($this->CanUserContribute($rr->id,"",$_SESSION["IdMember"])) {
						$rr->PossibleActions=$rr->PossibleActions."<br /><a href=\"polls/contribute/".$rr->id."\">".$words->getFormatted("polls_contribute")."</a>" ;
					} 
					
					array_push( $tt,$rr) ;

				}
				return($tt) ;
        
    } // end of LoadList


}




?>