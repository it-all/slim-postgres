<?php
declare(strict_types=1);

namespace SlimPostgres;

use Slim\Container;

/**
 * navigation for admin pages
 */
class AdminNavigation
{
    private $nav;
    private $container;

    function __construct(Container $container)
    {
        $this->container = $container;
        $this->setNav();
    }

    /** entire nav without regard to permissions */
    private function setNav()
    {
        $this->nav = [

            'System' => [
                'subSections' => [
                    'Administrators' => [
                        'route' => ROUTE_ADMINISTRATORS,
                        'authorization' => ADMINISTRATORS_VIEW_RESOURCE,
                        'subSections' => [

                            'Insert' => [
                                'route' => ROUTE_ADMINISTRATORS_INSERT,
                            ],

                            'Roles' => [
                                'route' => ROUTE_ADMINISTRATORS_ROLES,
                                'subSections' => [
                                    'Insert' => [
                                        'route' => ROUTE_ADMINISTRATORS_ROLES_INSERT,
                                    ],
                                    'Permissions' => [
                                        'route' => ROUTE_ADMINISTRATORS_PERMISSIONS,
                                        'subSections' => [
                                            'Insert' => [
                                                'route' => ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT,
                                            ],
                                        ]
                                    ]
                                ],
                            ],

                            'Login Attempts' => [
                                'route' => ROUTE_LOGIN_ATTEMPTS,
                            ],
                        ]
                    ],

                    'Events' => [
                        'route' => ROUTE_SYSTEM_EVENTS,
                    ]

                ]
            ],
            'Logout' => [
                'route' => ROUTE_LOGOUT,
            ]
        ];

        if (isset($this->container['settings']['adminNav'])) {
            if (!is_array($this->container['settings']['adminNav'])) {
                throw new \Exception("adminNav config must be array");
            }

            $this->nav = array_merge($this->container['settings']['adminNav'], $this->nav);
        }
    }

    // precedence:
    // 1. directly set by minimumPermissions key in the section
    // 2. by section link
    // 3. by section name
    private function getSectionPermissions(array $section, string $sectionName)
    {
        if (isset($section['permissions'])) {
            return $section['permissions'];
        }

        if (isset($section['route'])) {
            return $this->container->authorization->getPermissions($section['route']);
        }

        // by nav section - ie NAV_ADMIN_SYSTEM
        // note if nav section not defined constant evaluates to null, which results in base role permission (the default)
        return $this->container->authorization->getPermissions(constant('NAV_ADMIN_'.strtoupper(str_replace(" ", "_", $sectionName))));
    }

    /** add nav components as necessary based on user role */
    private function getSectionForUserRecurs(array $section, string $sectionName): array
    {
        // if there are section permissions and they are not met, do not put section in user's nav
        // if ($permissions = $this->getSectionPermissions($section, $sectionName)) {
        //     if (!$this->container->authorization->isAuthorized($permissions)) {
        //         return [];
        //     }
        // }

        // rebuild based on permissions
        $updatedSection = [];
        foreach ($section as $key => $value) {
            if ($key != 'subSections') {
                $updatedSection[$key] = $value;
            }
        }

        $updatedSubSections = [];
        if (isset($section['subSections'])) {
            foreach ($section['subSections'] as $subSectionName => $subSection) {

                $updatedSubSection = $this->getSectionForUserRecurs($subSection, $subSectionName);
                if (count($updatedSubSection) > 0) {
                    $updatedSubSections[$subSectionName] = $updatedSubSection;
                }
            }
        }

        if (count($updatedSubSections) > 0) {
            $updatedSection['subSections'] = $updatedSubSections;
        }

        return $updatedSection;

    }

    public function getNavForUser()
    {
        $nav = []; // rebuild nav sections based on authorization for this user

        foreach ($this->nav as $sectionName => $section) {
            $updatedSection = $this->getSectionForUserRecurs($section, $sectionName);
            if (count($updatedSection) > 0) {
                $nav[$sectionName] = $updatedSection;
            }
        }

        return $nav;
    }
}
