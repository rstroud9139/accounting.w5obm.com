<?php
// Defines report batches with URL templates.
// Placeholders: {year}, {month}, {start}, {end}
return array(
    'monthly' => array(
        'name' => 'Monthly Set',
        'reports' => array(
            array(
                'label' => 'Income Statement (Monthly)',
                'url' => '/accounting/reports/ytd_income_statement_monthly.php?year={year}&month={month}'
            ),
        ),
    ),
    'ytd' => array(
        'name' => 'Year-to-Date Set',
        'reports' => array(
            array(
                'label' => 'Income Statement (YTD)',
                'url' => '/accounting/reports/ytd_income_statement.php?year={year}&start={start}&end={end}'
            ),
        ),
    ),
    'annual' => array(
        'name' => 'Annual Set',
        'reports' => array(
            array(
                'label' => 'Income Statement (Annual)',
                'url' => '/accounting/reports/ytd_income_statement.php?year={year}&start={start}&end={end}'
            ),
        ),
    ),
);
