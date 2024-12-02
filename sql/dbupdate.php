<#1>
<?php
\minervis\plugins\LPFixx\Repository::getInstance()->installTables();
?>

<#2>
<?php
    if(!$ilDB->tableExists('cron_crnhk_lpfixx_log')){
        $fields_data = array(
            'id' => array(
                'type' => 'integer',
                'length' => 8,
                'notnull' => true
            ),
            'usr_id' => array(
                'type' => 'integer',
                'length' => 8,
                'notnull' => true
            ),
            'obj_id' => array(
                'type' => 'integer',
                'length' => 8,
                'notnull' => true
            ),
            'reason' => array(
                'type' => 'text',
                'length' => 256,
                'notnull' => false
            ),
            'created_at' => array(
                'type' => 'timestamp',
                'notnull' => false
            ),
            'status' => array(
                'type' => 'text',
                'length' => 256,
                'notnull' => false
            )
            
            
        );

        $ilDB->createTable("cron_crnhk_lpfixx_log", $fields_data);
        $ilDB->addPrimaryKey("cron_crnhk_lpfixx_log", array("id", ));
        $ilDB->createSequence("cron_crnhk_lpfixx_log"); 
    }

?>
