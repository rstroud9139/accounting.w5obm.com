<?php
class DashboardController extends BaseController
{
    public function index()
    {
        // Sample KPIs (replace with real queries later)
        $db = accounting_db_connection();
        $totalIncome = 0.0;
        $totalExpense = 0.0;
        try {
            $yr = date('Y');
            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN type='Income' THEN amount ELSE 0 END) AS inc,
                SUM(CASE WHEN type='Expense' THEN amount ELSE 0 END) AS exp
              FROM acc_transactions WHERE YEAR(transaction_date)=?");
            $stmt->bind_param('i', $yr);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $totalIncome = floatval($r['inc'] ?? 0);
            $totalExpense = floatval($r['exp'] ?? 0);
        } catch (Throwable $e) {
        }

        $this->render('dashboard/index', [
            'page_title' => 'Accounting Dashboard',
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
        ]);
    }
}
