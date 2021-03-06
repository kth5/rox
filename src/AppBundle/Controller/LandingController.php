<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Member;
use AppBundle\Form\SearchGotoLocationFormType;
use AppBundle\Form\SearchHomeLocationFormType;
use AppBundle\Model\CommunityNewsModel;
use AppBundle\Model\DonateModel;
use AppBundle\Model\LandingModel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LandingController extends Controller
{
    /**
     * @param Request $request
     *
     * @Route( "/widget/messages", name="/widget/messages")
     *
     * @return Response
     */
    public function showMessagesAction(Request $request)
    {
        $unread = $request->query->get('unread', false);
        /* Ignore query parameter all as $unread is set accordingly
        $all = $request->query->get('all', false);
        */

        $member = $this->getUser();

        $homeModel = new LandingModel($this->getDoctrine());
        $messages = $homeModel->getMessages($member, $unread, 4);

        $content = $this->render(':landing/widget:messages.html.twig', [
            'messages' => $messages,
        ]);

        return $content;
    }

    /**
     * @Route( "/widget/notifications", name="/widget/notifications")
     *
     * @return Response
     */
    public function showNotificationsAction()
    {
        $member = $this->getUser();

        $homeModel = new LandingModel($this->getDoctrine());
        $notifications = $homeModel->getNotifications($member, 5);

        $content = $this->render(':landing/widget:notifications.html.twig', [
            'notifications' => $notifications,
        ]);

        return $content;
    }

    /**
     * @param Request $request
     *
     * @Route( "/widget/threads", name="/widget/threads")
     *
     * @return Response
     */
    public function showThreadsAction(Request $request)
    {
        $groups = $request->query->get('groups');
        $forum = $request->query->get('forum');
        $following = $request->query->get('following');

        $member = $this->getUser();
        $homeModel = new LandingModel($this->getDoctrine());
        $threads = $homeModel->getThreads($member, $groups, $forum, $following, 4);

        $content = $this->render(':landing:widget/forums.html.twig', [
            'threads' => $threads,
        ]);

        return $content;
    }

    /**
     * @Route( "/widget/activities", name="/widget/activities")
     *
     * @return Response
     */
    public function showActivitiesAction()
    {
        $member = $this->getUser();
        $homeModel = new LandingModel($this->getDoctrine());
        $activities = $homeModel->getActivities($member, 4);

        $content = $this->render(':landing:widget/activities.html.twig', [
            'activities' => $activities,
        ]);

        return $content;
    }

    /**
     * @Route( "/widget/accommodation", name="/widget/accommodation")
     *
     * @return Response
     */
    public function setAccommodationAction(Request $request)
    {
        $accommodation = $request->request->get('accommodation');

        switch ($accommodation) {
            case Member::ACC_YES:
            case Member::ACC_MAYBE:
            case Member::ACC_NO:
                $valid = true;
                break;
            default:
                $valid = false;
        }

        $member = $this->getUser();
        if ($valid) {
            $landingModel = new LandingModel($this->getDoctrine());
            $member = $landingModel->updateMemberAccommodation($member, $accommodation);
        }

        // we need raw HTML and no response therefore we do not use the render method of the controller
        $profilePictureWithAccommodation = $this->container->get('twig')->render(':landing:widget/profilepicturewithaccommodation.html.twig', [
            'member' => $member,
        ]);

        $accommodationHtml = $this->container->get('twig')->render(':landing:widget/accommodation.html.twig', [
            'member' => $member,
        ]);

        return new JsonResponse([
            'profilePictureWithAccommodation' => $profilePictureWithAccommodation,
            'accommodationHtml' => $accommodationHtml,
        ]);
    }

    /**
     * Shows the landing page.
     *
     * \todo create controller and add routes there
     *
     * @Route("/landing", name="landingpage")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $member = $this->getUser();
        $donationModel = new DonateModel($this->getDoctrine());
        $campaignDetails = $donationModel->getStatForDonations();

        $homeModel = new LandingModel($this->getDoctrine());
        $travellersInArea = $homeModel->getTravellersInAreaOfMember($member);

        $communityNews = new CommunityNewsModel($this->getDoctrine());
        $latestNews = $communityNews->getLatest();

        // Prepare search form for home location link
        $data = $this->getSearchHomeLocationData($member);
        $searchHomeLocation = $this->createForm(SearchHomeLocationFormType::class, $data);

        // Prepare small search form
        $searchGotoLocation = $this->createForm(SearchGotoLocationFormType::class);

        $content = $this->render(':landing:landing.html.twig', [
                'title' => 'BeWelcome',
                'searchLocation' => $searchHomeLocation->createView(),
                'tinySearch' => $searchGotoLocation->createView(),
                'campaign' => [
                    'year' => $campaignDetails->year,
                    'yearNeeded' => $campaignDetails->YearNeededAmount,
                    'yearDonated' => $campaignDetails->YearDonation,
                ],
                'travellers' => $travellersInArea,
                'communityNews' => $latestNews,
        ]);

        return $content;
    }

    /**
     * @param Member $member
     *
     * @return array
     */
    private function getSearchHomeLocationData(Member $member)
    {
        $geo = $member->getCity();
        $data['search_geoname_id'] = $geo->getGeonameid();
        $data['search'] = $geo->getName();
        $data['search_latitude'] = $member->getLatitude();
        $data['search_longitude'] = $member->getLongitude();

        return $data;
    }
}
