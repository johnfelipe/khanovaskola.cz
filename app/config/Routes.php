<?php

use Nette\Application\Routers\Route;
use Nette\Utils\Strings;


class Routes
{

	public function setup($container)
	{
		$container->router[] = new Route('index.php', 'Front:Homepage:default', Route::ONE_WAY);

        $domain = $container->parameters['environment'] === 'production' ? 'khanovaskola.cz' : 'khan.l';

        /**
         * Tablet routes - duplicated because link creator must know the domain
         */
        $container->router[] = new Route("//tablet.$domain/prihlaseni", [
            'presenter' => 'Sign',
            'action' => 'inTablet',
        ]);
        $container->router[] = new Route("//tablet.$domain/odhlaseni", [
            'presenter' => 'Sign',
            'action' => 'out',
        ]);
        $container->router[] = new Route("//tablet.$domain/<presenter>[/<action>]", [
            'module' => 'Tablet',
            'presenter' => 'Homepage',
            'action' => 'default',
        ]);

        /**
		 * API
		 */
		$container->router[] = new Route('//api.<domain>.<top l|cz>/', [
			'module' => 'Api',
			'presenter' => 'Documentation',
		]);
		$container->router[] = new Route('//api.<domain>.<top l|cz>/<presenter>[/<id>[/<method>]]', [
			'module' => 'Api',
			'presenter' => 'Category',
		]);
		$container->router[] = new Route('css/dynamic.dcss', [
			'module' => 'Api',
			'presenter' => 'DynamicCss',
			'action' => 'default',
		]);

		/**
		 * SEO Category
		 */
		$categoryRoute = new SlugRoute('<id>[/<action>]', [
			'id' => [
				Route::FILTER_OUT => function ($id) use ($container) {
					if (!is_numeric($id)) {
						return $id;

					} else {
						return Strings::webalize($container->categories->findOneBy(['id' => $id])->label);
					}
				}
			],
			'module' => 'Front',
			'presenter' => 'Browse',
			'action' => [
				Route::VALUE => 'default',
				Route::FILTER_TABLE => [
					'upravit' => 'edit',
				]
			],
		]);
		$categoryRoute->init($container, 'id', 'categories');
		$container->router[] = $categoryRoute;

		/**
		 * SEO Video
		 */
		$videoRoute = new SlugRoute('video/<vid>[/<action>]', [
			'vid' => [
				Route::FILTER_OUT => function ($vid) use ($container) {
					if (!is_numeric($vid)) {
						return $vid;

					} else {
						return Strings::webalize($container->videos->findOneBy(['id' => $vid])->label);
					}
				}
			],
			'module' => 'Front',
			'presenter' => 'Watch',
			'action' => [
				Route::VALUE => 'default',
				Route::FILTER_TABLE => [
					'upravit' => 'edit',
				]
			],
		]);
		$videoRoute->init($container, 'vid', 'videos');
		$container->router[] = $videoRoute;

		/**
		 * SEO Cviceni
		 */
		$exRoute = new SlugRoute('cviceni/<eid>', [
			'eid' => [
				Route::FILTER_OUT => function ($eid) use ($container) {
					if (!is_numeric($eid)) {
						return $eid;

					} else {
						return Strings::webalize($container->exercises->findOneBy(['id' => $eid])->label);
					}
				}
			],
			'module' => 'Front',
			'presenter' => 'Exercise',
			'action' => 'default',
		]);
		$exRoute->init($container, 'eid', 'exercises');
		$container->router[] = $exRoute;

		/**
		 * Shortest add teacher url possible
		 */
		$container->router[] = new Route('u/<coach_id \d+>', [
			'module' => 'Front',
			'presenter' => 'Profile',
			'action' => 'confirmCoach',
		]);

        /**
         * Sitemap
         */
        $container->router[] = new Route('sitemap[!.xml]', [
            'presenter' => 'Sitemap',
            'action' => 'default',
        ]);

		/**
		 * Direct actions of sign presenter
		 */
		$container->router[] = new Route('<action (prihlaseni|odhlaseni|fb-auth|google-auth)>', [
			'presenter' => 'Sign',
			'action' => [
				Route::FILTER_TABLE => [
					'prihlaseni' => 'in',
					'odhlaseni' => 'out',
				]
			]
		]);

		/**
		 * Moderator module without tanslations
		 */
		$container->router[] = new Route('moderator/<presenter>/<action>[/<id>]', [
			'module' => 'Moderator',
			'presenter' => 'Dashboard',
			'action' => 'default',
		]);

		/**
		 * Coach module
		 */
		$container->router[] = new Route('ucit/<presenter>/<action>', [
			'module' => 'Coach',
			'presenter' => [
				Route::VALUE => 'Dashboard',
				Route::FILTER_TABLE => [
					'prehled' => 'Dashboard',
					'skupina' => 'Group',
					'student' => 'Profile',
					'ukol' => 'Task',
				],
			],
			'action' => [
				Route::VALUE => 'default',
				Route::FILTER_TABLE => [
					'pridat-studenty' => 'addStudents',
					'nemate-studenty' => 'noStudents',
					'upravit' => 'edit',
				],
			],
		]);

		/**
		 * Other presenters
		 */
		$container->router[] = new Route('<presenter>/<action>[/<id>]', [
			'module' => 'Front',
			'presenter' => [
				Route::VALUE => 'Homepage',
				Route::FILTER_TABLE => [
					'cviceni' => 'Exercise',
					'registrace' => 'Registration',
					'o-skole' => 'About',
					'kontakt' => 'Contact',
					'dobrovolnici' => 'Volunteer',
					'profil' => 'Profile',
				],
			],
			'action' => [
				Route::VALUE => 'default',
				Route::FILTER_TABLE => [
					'pravidla-pouziti' => 'tos',
					'osobni-udaje' => 'privacy',
					'ucit' => 'coach',
				],
			],
		]);
	}

}
