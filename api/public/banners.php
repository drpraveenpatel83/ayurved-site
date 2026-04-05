<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
jsonSuccess(getDB()->query("SELECT id,image_url,title,subtitle,link_url,color FROM banners WHERE is_active=1 ORDER BY display_order,id")->fetchAll());
