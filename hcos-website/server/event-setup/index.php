<?php
// Event Setup — admin. Reached only by its direct address; nothing links here.
require __DIR__ . '/../_app/bootstrap.php';
require HCOS_APP . '/admin/router.php';
admin_dispatch();
