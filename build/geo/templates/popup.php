<?php

/**
 * Geo Popup Template
 * This is a popup that is beeing used as an alternative to javascript based geo location selection
 * We redefine the methods of RoxPageView to configure this page.
 *
 * @package geo
 * @author Micha (bw: lupochen)
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL)
 * @version $Id$
 */
 ?>
 <div id="geoselector" style="text-align: left">
        <form method="POST" action="<?=$page_url?>" name="geo-form" id="geo-form" target="_self">
            <?=$callbacktag?>
          <fieldset id="location">
            
            <?php 
            /*if (isset($_SESSION['GeoVars']['geonameid'])) { 
                <p>Current location:</p>
                <ol class="geoloc plain floatbox">
                <li style="background-color: #f5f5f5; font-weight: bold; background-image: url(images/icons/tick.png);"><a id="href_4544349">
                <?=$_SESSION['GeoVars']['geonamename']?><br/>
                <img alt="United States" src="images/icons/flags/<?=$_SESSION['GeoVars']['geonamecountrycode']?>.png"/> 
                <span class="small"><?=$_SESSION['GeoVars']['countryname']?> / <?=$_SESSION['GeoVars']['admincode']?></span>
                </a></li>
                </ol>
             } */
            ?>

        <div class="subcolumns">
          <div class="c50l">
            <div class="subcl">
              <!-- Content of left block -->
                
              <div class="float_left">
              
              <ul class="floatbox">
                <label for="create-location"><?=$words->get('label_setlocation')?>:</label><br />
                <input type="text" name="create-location" id="create-location-nonjs" <?php
                echo isset($mem_redirect->location) ? 'value="'.htmlentities($mem_redirect->location, ENT_COMPAT, 'utf-8').'" ' : '';
                ?>
                 /> <input type="submit" id="btn-create-location-nonjs" class="button" value="<?=$words->get('label_search_location')?>" />
                <p class="desc"><?=$words->get('subline_location')?></p>
               </ul>
              </div>
            </div>
          </div>

          <div class="c50r">
            <div class="subcr">
              <!-- Content of right block -->
            </div>
          </div>
        </div>
        
          </fieldset>
        </form>
        
        
          <fieldset id="location_selection">
        <?php echo $locations_print; ?>
          </fieldset>
        
</div>