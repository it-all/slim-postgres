<?php

function navSection($key, $navItem)
{
    $navSection = '<li>';

    $navSection .= (isset($navItem['link'])) ? '<a href="'.$navItem['link'].'">'.$key.'</a>' : $key;
    if (isset($navItem['subSections']))     {
        $navSection .= '<a href="#" onclick="toggleDisplay(getElementById(\''.$key.'\'));togglePlusMinus(this);">+</a>';
        $navSection .= '<ul class="adminNavSubSection" id="'.$key.'">';
        foreach ($navItem['subSections'] as $subKey => $subNavItem) {
            $navSection .= navSection($subKey, $subNavItem);
        }
        $navSection .= '</ul>';
    }

    $navSection .= '</li>';
    return $navSection;
}

$nav = '';

if (isset($navigationItems)) {
    foreach ($navigationItems as $navKey => $navItem) {
        $nav .= navSection($navKey, $navItem);
    }
}

//
//{% macro recursiveNavSection(key, navigationItem) %}
//    {% import _self as self %}
//    <li>
//        {% if navigationItem.link %}
//            <a href="{{ path_for(navigationItem.link) }}">{{ key }}</a>
//        {% else %}
//            {{ key }}
//        {% endif %}
//        {% if navigationItem.subSections|length %}
//            <a href="#" onclick="toggleDisplay(getElementById('{{ key }}'));togglePlusMinus(this);">+</a>
//            <ul class="adminNavSubSection" id="{{ key }}">
//                {% for key, navigationItem in navigationItem.subSections %}
//                    {{ self.recursiveNavSection(key, navigationItem) }}
//                {% endfor %}
//            </ul>
//        {% endif %}
//    </li>
//{% endmacro %}
//
//{% from _self import recursiveNavSection %}
//
//{% if navigationItems %}
//    <ul>
//        {% for key, navigationItem in navigationItems %}
//            {{ recursiveNavSection(key, navigationItem) }}
//        {% endfor %}
//    </ul>
//{% endif %}