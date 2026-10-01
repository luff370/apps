<?php

return [
    'guest_limit' => (int) env('USER_ARCHIVE_GUEST_LIMIT', 10),
    'member_limit' => (int) env('USER_ARCHIVE_MEMBER_LIMIT', 5000),
    'expire_extra_days' => (int) env('USER_ARCHIVE_EXPIRE_EXTRA_DAYS', 30),
];
