<?php

declare(strict_types=1);

/*
 * Overrides for filament/query-builder's own strings. Laravel merges this over
 * the package file, so only the differing keys belong here.
 *
 * The package names its own machinery -- "rules", "groups", "blocks" -- which
 * describes the data structure rather than what an admin is building. Award
 * criteria are conditions, and an OR is an alternative set of them, so the
 * labels say that. The structure itself is the vendor's and is unchanged; only
 * the wording is.
 *
 * The AND/OR separators are deliberately left alone: they are the one part of
 * the scaffolding that already reads as logic rather than as plumbing.
 */
return [

    'form' => [

        'or_groups' => [

            'label' => 'Alternatives',

            'group' => [
                'label' => 'Alternative',
            ],

            'block' => [
                'label' => 'Any of these',
            ],

        ],

        'rules' => [
            'label' => 'Conditions',
        ],

    ],

    'no_rules' => '(No conditions)',

    'max_rules_reached_tooltip' => 'You have reached the maximum of :count conditions.',

    'actions' => [

        'add_rule' => [
            'label' => 'Add condition',
        ],

        'add_rule_group' => [
            'label' => 'Add alternative',
        ],

    ],

];
