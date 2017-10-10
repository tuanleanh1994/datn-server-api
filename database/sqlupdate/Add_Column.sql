IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='MEMB_INFO' AND COLUMN_NAME='bank_sliver' )
BEGIN
	Alter TABLE MEMB_INFO ADD bank_sliver int NOT NULL DEFAULT 0
	PRINT 'ADDED Column MEMB_INFO.bank_sliver'
END
ELSE BEGIN PRINT 'Exist Column MEMB_INFO.bank_sliver' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='MEMB_INFO' AND COLUMN_NAME='bank_sliver_lock' )
	BEGIN
		Alter TABLE MEMB_INFO ADD bank_sliver_lock int NOT NULL DEFAULT 0
		PRINT 'ADDED Column MEMB_INFO.bank_sliver_lock'
	END
ELSE BEGIN PRINT 'Exist Column MEMB_INFO.bank_sliver_lock' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='MEMB_INFO' AND COLUMN_NAME='bank_zen' )
	BEGIN
		Alter TABLE MEMB_INFO ADD bank_zen int NOT NULL DEFAULT 0
		PRINT 'ADDED Column MEMB_INFO.bank_zen'
	END
ELSE BEGIN PRINT 'Exist Column MEMB_INFO.bank_zen' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='MEMB_INFO' AND COLUMN_NAME='bank_jewel' )
	BEGIN
		Alter TABLE MEMB_INFO ADD bank_jewel int NOT NULL DEFAULT 0
		PRINT 'ADDED Column MEMB_INFO.bank_jewel'
	END
ELSE BEGIN PRINT 'Exist Column MEMB_INFO.bank_jewel' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='MEMB_INFO' AND COLUMN_NAME='bank_jewel_lock' )
	BEGIN
		Alter TABLE MEMB_INFO ADD bank_jewel_lock int NOT NULL DEFAULT 0
		PRINT 'ADDED Column MEMB_INFO.bank_jewel_lock'
	END
ELSE BEGIN PRINT 'Exist Column MEMB_INFO.bank_jewel_lock' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='Character' AND COLUMN_NAME='lock_item' )
	BEGIN
		Alter TABLE Character ADD lock_item tinyint NOT NULL DEFAULT 0
		PRINT 'ADDED Column Character.lock_item'
	END
ELSE BEGIN PRINT 'Exist Column Character.lock_item' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='Character' AND COLUMN_NAME='lock_item_code' )
	BEGIN
		Alter TABLE Character ADD lock_item_code varchar(50)
		PRINT 'ADDED Column Character.lock_item_code'
	END
ELSE BEGIN PRINT 'Exist Column Character.lock_item_code' END

IF NOT EXISTS ( SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='Character' AND COLUMN_NAME='point_reserve' )
	BEGIN
		Alter TABLE Character ADD point_reserve int NOT NULL DEFAULT 0
		PRINT 'ADDED Column Character.point_reserve'
	END
ELSE BEGIN PRINT 'Exist Column Character.point_reserve' END