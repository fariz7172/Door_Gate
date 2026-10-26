<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::statement("DROP TABLE IF EXISTS `usersxdevice`");
        DB::statement("CREATE TABLE `usersxdevice` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `userId` int(10) DEFAULT NULL,
  `gateId` int(10) DEFAULT NULL,
  `Lift1` tinyint(4) unsigned DEFAULT '0' COMMENT 'Setting Lift 1- 16',
  `Lift2` tinyint(4) unsigned DEFAULT '0' COMMENT 'Setting Lift 17- 32',
  `Lift3` tinyint(4) unsigned DEFAULT '0' COMMENT 'Setting Lift 33- 48',
  `Lift4` tinyint(4) unsigned DEFAULT '0' COMMENT 'Setting Lift 49- 64',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(): void { DB::statement("DROP TABLE IF EXISTS `usersxdevice`"); }
};
