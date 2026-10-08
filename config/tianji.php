<?php

return [

    'knowledge_path' => base_path('docs/tianji/knowledge/tianji.md'),

    'index_path' => base_path('docs/tianji/knowledge/index.json'),

    'system_prompt_path' => base_path('docs/tianji/SYSTEM_PROMPT.md'),

    // 当前应用只开放紫微知识。易经、阳宅仍留在知识包里，但不进入对话。
    'enabled_skills' => ['ziwei'],

    // 送给模型的最近消息条数，一问一答计 2 条。
    'history_messages' => 20,

    'history_read_limit' => 100,

    'session_list_limit' => 20,

    'max_content_length' => 2000,

    'max_report_bytes' => 30000,

];
