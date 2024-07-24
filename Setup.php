<?php

namespace TaylorJ\UserBlogs;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	public function installStep1()
    {
        $this->createTable('xf_taylorj_userblogs_blog', function (\XF\Db\Schema\Create $table)
        {
            $table->addColumn('id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('blog_title', 'varchar', 50);
            $table->addColumn('blog_description', 'varchar', 255);
            $table->addColumn('blog_creation_date', 'int')->setDefault(0);
            $table->addColumn('blog_last_post_date', 'int')->setDefault(0);
            $table->addColumn('blog_has_header', 'tinyint')->setDefault(0);
        });

    }

    public function installStep2()
    {
        $this->createTable('xf_taylorj_userblogs_blog_post', function (\XF\Db\Schema\Create $table)
        {
            $table->addColumn('id', 'int')->autoIncrement();
            $table->addColumn('blog_id', 'int');
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('blog_post_title', 'varchar', 50);
            $table->addColumn('blog_post_content', 'text');
            $table->addColumn('blog_post_date', 'int')->setDefault(0);
            $table->addColumn('blog_last_edit_date', 'int');
        });

    }
}