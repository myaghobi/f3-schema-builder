<?php

/**
 *  VDB Schema Builder Testing Suite
 *
 **/
class VDB_Tests extends F3instance {

	function run() {
        $this->set('title','Variable DB Schema Builder');

        // prevent errors breaking the render process,
        // set this to false for debugging purpose
       F3::set('QUIET',false);

        $dbs = array(
            'mysql' => new VDB(
                'mysql:host=localhost;port=3306;dbname=test','root','',
                array(PDO::ATTR_STRINGIFY_FETCHES => true)
            ),
            'sqlite' => new VDB(
                'sqlite::memory:'
            ),
            'pgsql' => new VDB(
                'pgsql:host=localhost;dbname=test','test','1234',
                array(PDO::ATTR_STRINGIFY_FETCHES => true)
            ),
        );

        $tname = 'test_table';

        foreach( $dbs as $type => $db) {

            $this->set('DB',$db);
            $type .= ': ';
            $db->dropTable($tname);

            // create table
            $cr_result = $db->createTable($tname);
            $result = $db->getTables();
            $this->expect(
                $cr_result == true && in_array($tname,$result) == true,
                $type.'create table'.$this->getTime(),
                $type.'could not create table'.$this->getTime()
            );

            foreach(array_keys($db->dataTypes) as $index=>$field) {
                // testing column types
                list($r1,$r2) = $db->table($tname,
                    function($table) use($index,$field) {
                        return $table->addCol('column_'.$index,$field);
                    },
                    function($table){ return $table->getCols(); });
                $this->expect(
                    $r1 == true && in_array('column_'.$index,$r2) == true,
                    $type.'adding column ['.$field.'], nullable'.$this->getTime(),
                    $type.'cannot add a nullable column of type:'.$field.$this->getTime()
                );
            }

            // adding some testing data
            $ax = new Axon($tname,$db);
            $ax->column_6 = 'hello world';
            $ax->save();
            $ax->reset();
            $result = $ax->afind();

            $this->expect(
                $result[0]['column_6'] == 'hello world',
                $type . 'added dummy data' . $this->getTime(),
                $type . 'no dummy data added' . $this->getTime()
            );

            // default value text, not nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('text_default_not_null','TEXT8',false,'foo bar'); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('text_default_not_null',array_keys($r2)) == true && $r2['text_default_not_null']['default'] == 'foo bar' && $r2['text_default_not_null']['null'] == false,
                $type.'adding column [TEXT8], not nullable with default value'.$this->getTime(),
                $type.'missmatching default value in not nullable [TEXT8] column'.$this->getTime()
            );

            $ax->sync($tname);
            $ax->column_6 = 'tantuay';
            $ax->save();
            $ax->reset();
            $result = $ax->afind();
            $this->expect(
                $result[1]['column_6'] == 'tantuay' && $result[1]['text_default_not_null'] == 'foo bar',
                $type . 'checking dummy data' . $this->getTime(),
                $type . 'dummy data broken' . $this->getTime()
            );

            // default value numeric, not nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('int_default_not_null','INT8',false,123); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('int_default_not_null',array_keys($r2)) == true && $r2['int_default_not_null']['default'] == 123 && $r2['int_default_not_null']['null'] == false,
                $type.'adding column [INT8], not nullable with default value'.$this->getTime(),
                $type.'missmatching default value in not nullable [INT8] column'.$this->getTime()
            );

            $ax->sync($tname);
            $ax->column_6 = 'test3';
            $ax->save();
            $ax->reset();
            $result = $ax->afind();
            $this->expect(
                $result[2]['column_6'] == 'test3' && $result[2]['int_default_not_null'] == '123',
                $type . 'checking dummy data' . $this->getTime(),
                $type . 'dummy data broken' . $this->getTime()
            );

            // default value text, nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('text_default_nullable','TEXT8',true,'foo bar'); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('text_default_nullable',array_keys($r2)) == true && $r2['text_default_nullable']['default'] == 'foo bar',
                $type.'adding column [TEXT8], nullable with default value'.$this->getTime(),
                $type.'missmatching default value in nullable [TEXT8] column'.$this->getTime()
            );

            $ax->sync($tname);
            $ax->column_6 = 'test4';
            $ax->save();
            $ax->reset();
            $ax->column_6 = 'test5';
//            $ax->text_default_nullable = 'test123'; //TODO: not possible in axon, right now?!
	        var_dump($ax->text_default_nullable);
            $ax->text_default_nullable = null; //TODO: not possible in axon, right now?!
            $ax->save();
            $ax->reset();
//            $db->exec("INSERT INTO $tname (column_6, text_default_nullable) VALUES('test5',NULL);");
            $result = $ax->afind();
            $this->expect(
                $result[3]['column_6'] == 'test4' && $result[3]['text_default_nullable'] == 'foo bar' &&
                $result[4]['column_6'] == 'test5' && $result[4]['text_default_nullable'] == null,
                $type.'checking dummy data'.$this->getTime(),
                $type.'dummy data broken'.$this->getTime()
            );

            // default value numeric, nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('int_default_nullable','INT8',true,123); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('int_default_nullable',array_keys($r2)) == true && $r2['int_default_nullable']['default'] == 123,
                $type.'adding column [INT8], nullable with default value'.$this->getTime(),
                $type.'missmatching default value in nullable [INT8] column'.$this->getTime()
            );

            // rename column
            list($r1,$r2) = $db->table($tname,
                function($table){ return $table->renameCol('text_default_not_null','title123'); },
                function($table){ return $table->getCols(); });
            $this->expect(
                $r1 == true &&
                in_array('title123',$r2) == true &&
                in_array('text_default_not_null',$r2) == false,
                $type.'renaming column'.$this->getTime(),
                $type.'cannot rename a column'.$this->getTime()
            );
            $db->table($tname, function($table){ return $table->renameCol('title123','text_default_not_null'); });

            // remove column
            list($r1,$r2) = $db->table($tname,
                function($table){ return $table->dropCol('title'); },
                function($table){ return $table->getCols(); });
            $this->expect(
                $r1 == true && in_array('title',$r2) == false,
                $type.'removng column'.$this->getTime(),
                $type.'cannot remove a column'.$this->getTime()
            );

            // rename table
            $db->dropTable('test123');
            $valid = $db->table($tname,
                function($table){ return $table->renameTable('test123'); });
            $this->expect(
                $valid == true && in_array('test123',$db->getTables()) == true && in_array($tname,$db->getTables()) == false,
                $type.'renaming table'.$this->getTime(),
                $type.'cannot rename a table'.$this->getTime()
            );
            $db->table('test123',
                function($table) use($tname) { $table->renameTable($tname); });

            // drop table
            $db->dropTable($tname);
            $this->expect(
                in_array($tname,$db->getTables()) == false,
                $type.'drop table'.$this->getTime(),
                $type.'cannot not drop table'.$this->getTime()
            );

            // adding composite primary keys
            $r1 = $db->create($tname,function($table){
                $table->addCol('version','INT8',false,1);
                $table->setPKs(array('id','version'));
                return $table->getCols(true);
            });
            $this->expect(
                $r1['id']['primary'] == true && $r1['version']['primary'] == true,
                $type . 'added composite primary-keys' . $this->getTime(),
                $type . 'composite primary-keys mismatching' . $this->getTime()
            );
            $this->expect(
                $r1['version']['default'] == '1',
                $type . 'default value on composite primary key' . $this->getTime(),
                $type . 'wrong default value definition on composite primary-key' . $this->getTime()
            );

            // more fields to composite primary key table
            $r1 = $db->table($tname, function ($table) {
                $table->addCol('title', 'TEXT8');
                $table->addCol('title2', 'TEXT8');
                $table->addCol('title_notnull', 'TEXT8', false, "foo");
                return $table->getCols(true);
            });
            $this->expect(
                array_key_exists('title',$r1) == true && array_key_exists('title_notnull', $r1) == true &&
                $r1['id']['primary'] == true && $r1['version']['primary'] == true,
                $type . 'added more fields to composite pk table' . $this->getTime(),
                $type . 'composite primary-keys failed after inserting more fields' . $this->getTime()
            );

            // testing primary keys with inserted data
            $ax = new Axon($tname,$db);
            $ax->title = 'test1';
            $ax->save();
            $ax->reset();

            $ax->id = $ax->_id;
            $ax->title = 'test2';
            $ax->version = 2;
            $ax->save();
            $ax->reset();

            $ax->title = 'test3';
            $ax->title2 = 'foobar';
            $ax->title_notnull = 'bar';
            $ax->save();

            $result = $ax->afind();
            $cpk_expected = array (
                array (
                    'id' => '1',
                    'version' => '1',
                    'title' => 'test1',
                    'title2' => NULL,
                    'title_notnull' => 'foo',
                ),
                array (
                    'id' => '1',
                    'version' => '2',
                    'title' => 'test2',
                    'title2' => NULL,
                    'title_notnull' => 'foo',
                ),
                array (
                    'id' => '2',
                    'version' => '1',
                    'title' => 'test3',
                    'title2' => 'foobar',
                    'title_notnull' => 'bar',
                ),
            );
            $this->expect(
                json_encode($result) == json_encode($cpk_expected),
                $type.'added items with composite primary-keys'.$this->getTime(),
                $type.'wrong result for composite primary-key items'.$this->getTime()
            );



            $db->dropTable($tname);

        }
        F3::set('QUIET',false);
        echo $this->render('basic/results.htm');
    }

    var $roundTime = 0;
    private function getTime() {
        $time = microtime(TRUE)-$this->get('timer') - $this->roundTime;
        $this->roundTime = microtime(TRUE)-$this->get('timer');
        return ' [ '.sprintf('%.3f',$time).'s ]';
    }

}