<?php
require_once dirname(__DIR__) . '/src/config.php';
db()->exec('ALTER TABLE family_members ADD COLUMN IF NOT EXISTS sort_order INTEGER DEFAULT 0 NOT NULL');
db()->exec('ALTER TABLE weekly_summaries ADD COLUMN IF NOT EXISTS paid_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL');
echo "Klart!";
