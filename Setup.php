<?php

namespace TaylorJ\Blogs;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	public function installStep1()
    {
        $this->createTable('xf_taylorj_blogs_blog', function (\XF\Db\Schema\Create $table)
        {
            $table->addColumn('blog_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('blog_title', 'varchar', 50);
            $table->addColumn('blog_description', 'varchar', 255);
            $table->addColumn('blog_creation_date', 'int')->setDefault(0);
            $table->addColumn('blog_last_post_date', 'int')->setDefault(0);
            $table->addColumn('blog_has_header', 'tinyint')->setDefault(0);
			$table->addColumn('blog_post_count', 'int')->setDefault(0);
			$table->addColumn('blog_post_state', 'varchar')->setDefault('visible');
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
        });

    }

    public function installStep2()
    {
        $this->createTable('xf_taylorj_blogs_blog_post', function (\XF\Db\Schema\Create $table)
        {
            $table->addColumn('blog_post_id', 'int')->autoIncrement();
            $table->addColumn('blog_id', 'int');
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('blog_post_title', 'varchar', 50);
            $table->addColumn('blog_post_content', 'text');
            $table->addColumn('blog_post_date', 'int')->setDefault(0);
            $table->addColumn('blog_post_last_edit_date', 'int')->setDefault(0);
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
            $table->addColumn('view_count', 'int')->setDefault(0);
        });

    }
    
    public function installStep3()
    {
        $this->createTable('xf_taylorj_blogs_blog_post_view', function (\XF\Db\Schema\Create $table)
        {
            $table->engine('MEMORY');

            $table->addColumn('blog_post_id', 'int');
            $table->addColumn('total', 'int');
            $table->addPrimaryKey('blog_post_id');
        });
    }
    
    public function installStep4()
    {
        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('taylorj_blogs_blog_count', 'int')->setDefault(0);
        });
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_taylorj_blogs_blog');
        $sm->dropTable('xf_taylorj_blogs_blog_blog_post');
        $sm->dropTable('xf_taylorj_blogs_blog_blog_post_view');
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('taylorj_blogs_blog_count');
        });
    }
}