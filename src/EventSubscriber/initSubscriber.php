<?php

namespace Drupal\facet_granular_date\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Class initSubscriber.
 *
 * This is used in conjuction with the newly created facet date granualar
 * processor.
 * Redirects to search in the following scenario - With 2018 as an example.
 *
 * Click 2018
 * Click March 2018
 * Click 2018.
 *
 * This behaviour will cause issues without this since the second 2018 click
 * is removing that filter, which keeping the Month one.
 */
class initSubscriber implements EventSubscriberInterface {


    /**
     * Constructs a new initSubscriber object.
     */
    public function __construct() {

    }

    /**
     * {@inheritdoc}
     */
    static function getSubscribedEvents() {
        $events['kernel.request'] = ['initEvent'];

        return $events;
    }

    /**
     * This method is called whenever the initEvent event is
     * dispatched.
     *
     * TODO - This needs refactoing. It's using the issue date (A specific
     * field for the site this was builts for.
     *
     * Needs to get all the facets in the params and check them properly
     * without site specific shortcuts.
     *
     * @param GetResponseEvent $event
     * @return $event
     */
    public function initEvent(Event $event) {

        $params = $event->getRequest()->query->all();
        $pathinfo = $event->getRequest()->getPathInfo();
        $yearRemoved = false;
        // Keep track of how many issue date facets have been found in the
        // request params.
        $issueDateFacets = 0;
        if (!empty($params['f'])) {
            foreach ($params['f'] as $param) {
                $pos = strpos($param, 'issue_date');
                if($pos !== false) {
                    $issueDateFacets++;
                }
                $pos2 = strpos($param, '-');
                if ($pos !== false && $pos2 !== false) {
                    // The
                    $yearRemoved = true;
                }
            }
            if (!empty($params['f']) && $pathinfo == '/search' && $yearRemoved && $issueDateFacets == 1) {
                $addUrlParams = '';
                foreach ($params['f'] as $key => $param) {
                    $pos = strpos($param, 'issue_date');
                    if($pos === false) {
                        $addUrlParams .= rawurlencode('f[' . $key . '])') . '=' . rawurlencode($param) . '&';
                    }
                }
                $addUrlParams = rtrim($addUrlParams, "&");
                //$addUrlParams = rawurlencode($addUrlParams);
                $addUrlParams = '?' . $addUrlParams;
                $response = new RedirectResponse('/search' . $addUrlParams, 302);
                kint([$response, $params['f'], $addUrlParams]);
                //die();
                $event->setResponse($response);
                return $event;
            }
        }
        return $event;
    }

}
