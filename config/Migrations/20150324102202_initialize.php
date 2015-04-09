<?php
use Phinx\Migration\AbstractMigration;

class Initialize extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Uncomment this method if you would like to use it.
     *
     */
    public function change()
    {
    }

    /**
     * Migrate Up.
     */
    public function up()
    {
        if (!$this->hasTable('images')) {
            $users = $this->table('images', ['id' => false, 'primary_key' => ['id']]);
            $users
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('foreign_key', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('model', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('field', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('filename', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('mime', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('size', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('created', 'datetime', ['null' => false])
                ->save();
        }
        else {
            throw new Exception('Table "images" already exists.');
        }
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        if($this->hasTable('images')) {
            $this->dropTable('images');
        }
    }
}
