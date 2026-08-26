<?php

/**
 * Wrapper entry point retained because the repository/install directory name
 * (`OJS-MailGuard`) is intentionally different from the PHP namespace segment
 * (`mailGuard`). PKP's plugin installer supports this wrapper path explicitly.
 */

require_once __DIR__ . '/MailGuardPlugin.php';

return new \APP\plugins\generic\mailGuard\MailGuardPlugin();
