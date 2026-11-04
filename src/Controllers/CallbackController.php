<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Logger;
use App\Http\Controller;
use App\Http\Response;
use App\Payment\SslCommerz;
use App\Security\Sanitizer;
use App\Services\PassService;
use App\Services\PaymentService;

/**
 * Endpoints SSLCommerz calls after the buyer leaves our site. They carry no
 * session and no CSRF token, so they accept nothing at face value: the val_id is
 * re-verified against the gateway before an order moves to paid.
 */
final class CallbackController extends Controller
{
    public function success(): Response
    {
        $tranId = $this->field('tran_id');
        $valId = $this->field('val_id');

        if ($tranId === '' || $valId === '') {
            return $this->redirect('/checkout.html?payment=invalid');
        }

        $result = $this->service()->settle($tranId, $valId, 'return');

        if ($result['ok']) {
            return $this->redirect('/dashboard.html?payment=success');
        }

        Logger::warning('Success callback did not settle', ['tran_id' => $tranId, 'reason' => $result['reason']]);

        return $this->redirect('/checkout.html?payment=failed');
    }

    public function fail(): Response
    {
        $this->service()->markFailed($this->field('tran_id'), 'buyer_failed');

        return $this->redirect('/checkout.html?payment=failed');
    }

    public function cancel(): Response
    {
        $this->service()->markCancelled($this->field('tran_id'));

        return $this->redirect('/checkout.html?payment=cancelled');
    }

    /** Server-to-server notification. Always acknowledged so the gateway stops retrying. */
    public function ipn(): Response
    {
        $tranId = $this->field('tran_id');
        $valId = $this->field('val_id');
        $status = strtoupper($this->field('status'));

        if ($tranId === '') {
            return $this->json(['ack' => true]);
        }

        $service = $this->service();

        if ($status === 'VALID' && $valId !== '') {
            $result = $service->settle($tranId, $valId, 'ipn');
            Logger::info('IPN processed', ['tran_id' => $tranId, 'reason' => $result['reason']]);
        } elseif ($status === 'CANCELLED') {
            $service->markCancelled($tranId);
        } elseif (in_array($status, ['FAILED', 'INVALID'], true)) {
            $service->markFailed($tranId, strtolower($status) . '_via_ipn');
        }

        return $this->json(['ack' => true]);
    }

    private function field(string $name): string
    {
        $value = $_POST[$name] ?? $this->request->query($name) ?? '';

        return substr(Sanitizer::text($value, 120), 0, 120);
    }

    private function service(): PaymentService
    {
        return new PaymentService(SslCommerz::make(), new PassService());
    }

    private function redirect(string $path): Response
    {
        return Response::redirect(rtrim((string) Config::get('urls.frontend'), '/') . $path);
    }
}
