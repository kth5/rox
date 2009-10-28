<?php


//------------------------------------------------------------------------------------
/**
 * AvatarsPage shows all avatar pictures of a member
 *
 */


class GalleryAvatarsPage extends GalleryBasePage
{

    protected function getSubmenuActiveItem()
    {
        return 'overview';
    }

    protected function teaserHeadline() {
        return '<a href="gallery">'.$this->getWords()->getBuffered('Gallery').'</a>';
    }
}

