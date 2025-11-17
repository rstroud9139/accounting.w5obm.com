<?php

class AccountingService
{
    public function validateAccountData($data)
    {
        $errors = array();
        if (empty($data['name'])) $errors[] = 'Name is required';
        if (empty($data['type'])) $errors[] = 'Type is required';
        // code optional but recommended
        return $errors;
    }

    public function validateTransactionData($data)
    {
        $errors = array();
        if (empty($data['transaction_date']) || !strtotime($data['transaction_date'])) {
            $errors[] = 'Valid date required';
        }
        if (empty($data['type']) || !in_array($data['type'], array('Income', 'Expense'), true)) {
            $errors[] = 'Type must be Income or Expense';
        }
        if (!isset($data['amount']) || !is_numeric($data['amount'])) {
            $errors[] = 'Amount must be numeric';
        }
        return $errors;
    }
}
