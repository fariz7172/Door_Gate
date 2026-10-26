<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::statement("DROP TABLE IF EXISTS `usersprofile`");
        DB::statement("CREATE TABLE `usersprofile` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ACC_MODE` tinyint(4) DEFAULT '0' COMMENT 'Sementara tidak usah muncul di UI',
  `PIN` int(10) unsigned DEFAULT NULL COMMENT 'Password',
  `DOOR_GRP` int(5) unsigned DEFAULT '0' COMMENT 'Sementara tidak usah muncul di UI',
  `BEGIN_DATE` datetime DEFAULT CURRENT_TIMESTAMP,
  `END_DATE` datetime DEFAULT '2079-12-31 23:59:59',
  `BIRTHDAY` date DEFAULT NULL,
  `Depid` int(11) DEFAULT '1' COMMENT 'DepartementID',
  `MemberNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Link Ke Member Dreampos, tidak usah muncul di UI',
  `Branchid` int(11) DEFAULT '1' COMMENT 'branchID',
  `photo` mediumblob COMMENT 'photo',
  `Card` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomerkartu',
  `NoIdentitas` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomer Identitas',
  `Timezone` int(11) DEFAULT '0',
  `shiftpatternID` int(4) DEFAULT '0',
  `PASSWORD` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=Staff/User, 1=Member, 2=Personal Trainer',
  `flag` int(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ADDR_of_CTL` (`ID`),
  KEY `user_names` (`NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='v4'");
    }
    public function down(): void { DB::statement("DROP TABLE IF EXISTS `usersprofile`"); }
};
