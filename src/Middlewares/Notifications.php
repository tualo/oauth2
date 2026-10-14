<?php

namespace Tualo\Office\Oauth2\Middlewares;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Notifications;
use Tualo\Office\Basic\IMiddleware;

class NotificationsMiddleware implements IMiddleware
{
    private const PENDING_SESSION_KEY = 'oauth2_pending_requests';

    public static function register()
    {
        App::use('oauth2', function () {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $pendingRequests = $_SESSION[self::PENDING_SESSION_KEY] ?? [];
            if (!is_array($pendingRequests)) {
                throw new \UnexpectedValueException('The pending OAuth notification queue is malformed.');
            }
            if ($pendingRequests === []) {
                return;
            }

            foreach ($pendingRequests as $pendingRequest) {
                if (
                    !is_array($pendingRequest)
                    || !isset($pendingRequest['title'], $pendingRequest['message'], $pendingRequest['type'])
                    || !is_string($pendingRequest['title'])
                    || !is_string($pendingRequest['message'])
                    || !is_string($pendingRequest['type'])
                    || !isset($pendingRequest['redirectUrl'])
                    || !is_string($pendingRequest['redirectUrl'])
                ) {
                    throw new \UnexpectedValueException('A pending OAuth notification is malformed.');
                }

                Notifications::getInstance()->addNotification(
                    $pendingRequest['title'],
                    $pendingRequest['message'],
                    $pendingRequest['type'],
                    $pendingRequest['redirectUrl']
                );
            }

            // Clear the pending OAuth notifications from the session
            // unset($_SESSION[self::PENDING_SESSION_KEY]);
        }, -50);
    }
}
