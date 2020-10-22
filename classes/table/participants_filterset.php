<?php

namespace mod_reengagement\table;

use core_table\local\filter\boolean_filter;
use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;

class participants_filterset extends filterset {
    /**
     * Get the required filters.
     *
     * The required filters are the course module id and the course id.
     *
     * @return array.
     */
    public function get_required_filters(): array {
        return [
            'cmid' => integer_filter::class,
            'courseid' => integer_filter::class,
        ];
    }

    /**
     * Get the optional filters.
     *
     * These are:
     * - accesssince;
     * - enrolments;
     * - groups;
     * - keywords;
     * - roles; and
     * - status.
     *
     * @return array
     */
    public function get_optional_filters(): array {
        return [
            'accesssince' => integer_filter::class,
            'enrolments' => integer_filter::class,
            'groups' => integer_filter::class,
            'keywords' => string_filter::class,
            'roles' => integer_filter::class,
            'status' => integer_filter::class,
        ];
    }
}