<?php

namespace TaylorJ\Blogs;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$this->createTable('xf_taylorj_blogs_blog', function (Create $table)
		{
			$table->addColumn('blog_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('blog_title', 'varchar', 50);
			$table->addColumn('blog_description', 'varchar', 255);
			$table->addColumn('blog_creation_date', 'int')->setDefault(0);
			$table->addColumn('blog_last_post_date', 'int')->setDefault(0);
			$table->addColumn('blog_has_header', 'tinyint')->setDefault(0);
			$table->addColumn('blog_state', 'enum')->values(['visible', 'moderated', 'deleted'])->setDefault('visible');
			$table->addColumn('blog_post_count', 'int')->setDefault(0);
			$table->addKey('blog_last_post_date');
		});

		$this->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('taylorj_blogs_blog_count', 'int')->setDefault(0);
		});
	}

	public function installStep2()
	{
		$this->createTable('xf_taylorj_blogs_blog_post', function (Create $table)
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
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('scheduled_post_date_time', 'int')->nullable();
			$table->addColumn('blog_post_state', 'enum')->values(['visible', 'scheduled', 'draft', 'moderated', 'deleted'])->setDefault('visible');
			$table->addColumn('discussion_thread_id', 'int')->setDefault(0);
			$table->addColumn('tags', 'mediumblob');
		});

		$this->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('taylorj_blogs_blog_post_count', 'int')->setDefault(0);
		});
	}

	public function installStep3()
	{
		$this->createTable('xf_taylorj_blogs_blog_post_view', function (Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('blog_post_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('blog_post_id');
		});
	}

	public function installStep4()
	{
		$this->schemaManager()->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('taylorj_blogs_blog_count', 'int')->setDefault(0);
			$table->addColumn('taylorj_blogs_blog_post_count', 'int')->setDefault(0);
			$table->addKey('taylorj_blogs_blog_count', 'blog_count');
			$table->addKey('taylorj_blogs_blog_post_count', 'blog_post_count');
		});
	}

	public function installStep5()
	{
		$this->createTable('xf_taylorj_blogs_blog_watch', function (Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('blog_id', 'int');
		});
	}

	public function installStep6()
	{
		$this->insertThreadType('blogPost', 'TaylorJ\Blogs:BlogPost', 'TaylorJ/Blogs');
	}

	public function upgrade1000034Step1()
	{
		$this->alterTable('xf_taylorj_blogs_blog_post', function (Alter $table)
		{
			$table->addColumn('blog_post_state', 'varchar')->setDefault('visible');
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
		});
	}

	public function upgrade1000035Step1()
	{
		$this->alterTable('xf_taylorj_blogs_blog_post', function (Alter $table)
		{
			$table->addColumn('scheduled_post_date_time', 'int')->nullable();
			$table->changeColumn('user_id', 'int');
			$table->changeColumn('blog_id', 'int');
			$table->changeColumn('blog_post_state', 'enum')->values(['visible', 'scheduled'])->setDefault('visible');
		});

		$this->createTable('xf_taylorj_blogs_blog_watch', function (Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('blog_id', 'int');
		});
	}

	public function upgrade1000036Step1()
	{
		$this->alterTable('xf_taylorj_blogs_blog_post', function (Alter $table)
		{
			$table->changeColumn('blog_post_state', 'enum')->values(['visible', 'scheduled', 'draft'])->setDefault('visible');
			$table->addColumn('discussion_thread_id', 'int')->setDefault(0);
		});

		$this->insertThreadType('blogPost', 'TaylorJ\Blogs:BlogPost', 'TaylorJ/Blogs');
	}

	public function upgrade1000036Step2()
	{
		$this->giveBlogPostComments();
	}

	public function upgrade1000038Step1()
	{
		$this->alterTable('xf_taylorj_blogs_blog_post', function (Alter $table)
		{
			$table->changeColumn('blog_post_state', 'enum')->values(['visible', 'scheduled', 'draft'])->setDefault('visible');
		});
	}

	public function upgrade1010070Step1()
	{
		$this->alterTable('xf_taylorj_blogs_blog_post', function (Alter $table)
		{
			$table->changeColumn('blog_post_state', 'enum')->values(['visible', 'scheduled', 'draft', 'moderated', 'deleted'])->setDefault('visible');
			$table->addColumn('tags', 'mediumblob');
		});
	}

	public function upgrade1010070Step2()
	{
		$this->alterTable('xf_taylorj_blogs_blog', function (Alter $table)
		{
			$table->addColumn('blog_state', 'enum')->values(['visible', 'moderated', 'deleted'])->setDefault('visible');
		});
	}

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		$this->changeDiscussionType();

		$sm->dropTable('xf_taylorj_blogs_blog');
		$sm->dropTable('xf_taylorj_blogs_blog_post');
		$sm->dropTable('xf_taylorj_blogs_blog_post_view');
	}

	public function uninstallStep2()
	{
		$sm = $this->schemaManager();
		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->dropColumns('taylorj_blogs_blog_count');
			$table->dropColumns('taylorj_blogs_blog_post_count');
		});
	}

	public function uninstallStep3()
	{
		$sm = $this->schemaManager();
		$sm->dropTable('xf_taylorj_blogs_blog_watch');
	}

	public function uninstallStep4()
	{
		$contentTypes = ['blogPost'];

		$this->uninstallContentTypeData($contentTypes);
	}

	public function giveBlogPostComments()
	{
		$blogPosts = \XF::app()->finder('TaylorJ\Blogs:BlogPost')->fetch();

		foreach ($blogPosts AS $blogPost)
		{
			if ($blogPost->discussion_thread_id == 0)
			{
				$creator = Utils::setupBlogPostThreadCreation($blogPost);
				if ($creator && $creator->validate())
				{
					$thread = $creator->save();
					$blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
					Utils::afterResourceThreadCreated($thread);
				}
			}
		}
	}

	private function changeDiscussionType()
	{
		$db = $this->db();
		$db->query("
            UPDATE xf_thread SET discussion_type = '' WHERE discussion_type = 'blogPost'
		");
	}
}
