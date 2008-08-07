<?php
/*

Copyright (c) 2007 BeVolunteer

This file is part of BW Rox.

BW Rox is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

BW Rox is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/> or
write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330,
Boston, MA  02111-1307, USA.

*/
/**
 * signup controller
 *
 * @package signup
 * @author Felix van Hove <fvanhove@gmx.de>
 */
class SignupController extends RoxControllerBase {

    /**
     * Index function
     *
     * Currently the index consists of following possible requests:
     * register    - registration form to page content
     * confirm   - confirmation redirect to signup
     *
     * @param void
     */
    public function index($args = false) {

        $request = $args->request;
        $model = new SignupModel();
        
        if (isset($_SESSION['IdMember']) && !MOD_right::get()->hasRight('words')) {
            
            $this->redirect('bw/member.php?cid='.$_SESSION['Username']);
            
        } else switch (isset($request[1]) ? $request[1] : '') {
        
            // copied from TB:
            // checks e-mail address for validity and availability
            case 'checkemail':
                // ignore current request, so we can use the last request
                PRequest::ignoreCurrentRequest();
                if (!isset($_GET['email'])) {
                    echo '0';
                    PPHP::PExit();
                }
                if (!PFunctions::isEmailAddress($_GET['email'])) {
                    echo '0';
                    PPHP::PExit();
                }
                echo (bool)!$model->takeCareForNonUniqueEmailAddress($_GET['email']);
                PPHP::PExit();
                break;
                
            // copied from TB:
            // checks handle for validity and availability
            case 'checkhandle':
                // ignore current request, so we can use the last request
                PRequest::ignoreCurrentRequest();
                if (!isset($request[2])) {
                    echo '0';
                    PPHP::PExit();
                }
                if (!preg_match(User::HANDLE_PREGEXP, $request[2])) {
                    echo '0';
                    PPHP::PExit();
                }
                if (strpos($request[2], 'xn--') !== false) { // Don't allow IDN-Prefixes
                    echo '0';
                    PPHP::PExit();
                }
                echo (bool)!$model->handleInUse($request[2]);
                PPHP::PExit();
                break;
                
            case 'getRegions':
            // ignore current request, so we can use the last request
            PRequest::ignoreCurrentRequest();
            if (!isset($request[2])) {
                PPHP::PExit();
            }
            $Geo = new GeoController;
            $locations = $model->getRegions($country);
            $out = '<select name="d_geoname" id="d_geoname" onchange="javascript: updateGeonames();">
                <option value="">None</option>';
            foreach ($locations as $code => $location) {
                $out .= '<option value="'.$code.'"'.($code == "$preselect" ? ' selected="selected"' : '').'>'.$location.'</option>';
            }
            $out .= '</select>';
            return $out;
            PPHP::PExit();
            break;
                
            case 'locationdropdowns':
            // ignore current request, so we can use the last request
            PRequest::ignoreCurrentRequest();
            if (!isset($request[2])) {
                PPHP::PExit();
            }
            $locations = $model->getAllLocations($country, $areacode);
            $out = '<select name="d_geoname" id="d_geoname" onchange="javascript: updateGeonames();">
                <option value="">None</option>';
            foreach ($locations as $code => $location) {
                $out .= '<option value="'.$code.'"'.($code == "$preselect" ? ' selected="selected"' : '').'>'.$location.'</option>';
            }
            $out .= '</select>';
            return $out;
            PPHP::PExit();
            break;
            
            case 'terms':
                // the termsandconditions popup
                $page = new SignupTermsPopup();
                break;
                
            case 'privacy':
                $page = new SignupPrivacyPopup();
                break;
            
            case 'mailconfirm':  // or give it a different name?
                // this happens when you click the link in the confirmation email
                if (!isset($request[2])) {
                    // can't continue
                    $page = new SignupMailConfirmPage_linkIsInvalid();
                } else if (!$process = $model->getProcess($request[2])) {
                    // process id invalid
                    $page = new SignupMailConfirmPage_linkIsInvalid();
                } else {
                    // yeah, we can continue the process!
                    $page = new SignupMailConfirmPage();
                    $page->process = $process;
                }
                break;
                
            case 'finish':
                // what now?
                
            default:
                
                $page = new SignupPage();
                $page->step = (isset($request[1]) && $request[1]) ? $request[1] : '1';
                $page->model = $model;
                

            /*
                case 'confirm':
                ob_start();
                $username = "";
                $email = "";
                $this->_view->confirmation($username, $email);
                $str = ob_get_contents();
                ob_end_clean();
                $P = PVars::getObj('page');
                $P->content .= $str;
                break;
            */
        }
        
        return $page;
    }
    
    
    public function signupFormCallback($args, $action, $mem_redirect, $mem_resend)
    {

        
        //$mem_redirect->post = $vars;
        foreach ($args->post as $key => $value) {
            $_SESSION['SignupBWVars'][$key] = $value;
        }
        $vars = $_SESSION['SignupBWVars'];
        $request = $args->request;
        
        if (isset($request[1]) && $request[1] == '4') {
            $model = new SignupModel();
            
            $errors = $model->checkRegistrationForm($vars);
            
            if (count($errors) > 0) {
                // show form again
                $_SESSION['SignupBWVars']['errors'] = $errors;
                $mem_redirect->post = $vars;
                return false;
            }
            $model->polishFormValues($vars);
            
            if (!$idTB = $model->registerTBMember($vars)) {
                // MyTB registration didn't work
            } else {
                // signup on MyTB successful, yeah.
                $id = $model->registerBWMember($vars);
                $_SESSION['IdMember'] = $id;
                $idTB = $id;
                
                $vars['feedback'] .= 
                    $model->takeCareForNonUniqueEmailAddress($vars['email']);
            
                $vars['feedback'] .=
                    $model->takeCareForComputerUsedByBWMember();
                
                $model->writeFeedback($vars['feedback']);
                                        
                $View = new SignupView($model);
                // TODO: BW 2007-08-19: $_SYSHCVOL['EmailDomainName']
                define('DOMAIN_MESSAGE_ID', 'bewelcome.org');    // TODO: config
                $View->registerMail($idTB);
                $View->signupTeamMail($vars);
                
                return PVars::getObj('env')->baseuri.'signup/register/finish';
            }
        }
        return false;        
    }
}
?>
