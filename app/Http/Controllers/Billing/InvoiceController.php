<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\FinancialTransaction;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class InvoiceController extends Controller
{
    /**
     * Display invoice as HTML (user must own the transaction).
     */
    public function show(int $id)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $transaction = FinancialTransaction::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['subscription.plan', 'user'])
            ->first();

        if (!$transaction) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Invoice not found',
                'status' => 404,
            ]);
        }

        return view('invoices.show', [
            'transaction' => $transaction,
            'subscription' => $transaction->subscription,
            'user' => $transaction->user,
        ]);
    }

    /**
     * Download invoice as PDF (user must own the transaction).
     */
    public function download(int $id)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $transaction = FinancialTransaction::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['subscription.plan', 'user'])
            ->first();

        if (!$transaction) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Invoice not found',
                'status' => 404,
            ]);
        }

        $html = view('invoices.show', [
            'transaction' => $transaction,
            'subscription' => $transaction->subscription,
            'user' => $transaction->user,
        ])->render();

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);
            $mpdf->WriteHTML($html);
            $pdf = $mpdf->Output('', 'S');
            $filename = 'invoice-' . $transaction->id . '.pdf';
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'status' => 500,
            ]);
        }
    }
}
