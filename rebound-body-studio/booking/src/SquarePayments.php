<?php
declare(strict_types=1);
namespace Rebound;
if (PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {http_response_code(404);exit;}

final class SquarePayments
{
    public function __construct(private Db $db, private Packages $packages, private SquareClient $square) {}

    public function status(): array
    {
        return ['enabled'=>$this->square->enabled(), 'connected'=>$this->square->configured(),
            'environment'=>$this->square->configured() ? $this->square->value('environment') : null,
            'account_email'=>'massage@reboundbodystudio.com'];
    }

    public function checkout(string $token): array
    {
        $id=$this->packages->authorizedPurchase($token);
        if (!$this->square->enabled()) throw new \RuntimeException('Online payment is not available yet. Please arrange payment with the studio.');
        $row=$this->db->transact(function() use($id) {
            $p=$this->db->one('SELECT pp.status,o.* FROM package_purchases pp JOIN package_orders o ON o.purchase_id=pp.id WHERE pp.id=?',[$id]);
            if (!$p || $p['status']!=='pending_payment') throw new \RuntimeException('This package is no longer awaiting payment. Refresh your balance.');
            $row=$this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
            if ($row) return $row;
            $site=rtrim($this->db->setting('site_url',''),'/');
            if (parse_url($site,PHP_URL_SCHEME)!=='https' || !filter_var($site,FILTER_VALIDATE_URL)) throw new \RuntimeException('The studio payment return address needs to be configured.');
            $key='rebound-'.bin2hex(random_bytes(24));
            $body=['idempotency_key'=>$key,'order'=>['location_id'=>$this->square->value('location_id'),
                'reference_id'=>'rebound-package-'.$id,'line_items'=>[['name'=>$p['package_name'],'quantity'=>'1',
                    'base_price_money'=>['amount'=>(int)$p['quoted_price_cents'],'currency'=>'USD']]]],
                'checkout_options'=>['allow_tipping'=>false,'ask_for_shipping_address'=>false,
                    'enable_coupon'=>false,'enable_loyalty'=>false,
                    'redirect_url'=>$site.'/booking/public/packages.html?v=20260912-square-3#square-return'],
                'payment_note'=>'Rebound package order #'.$id];
            $this->db->run('INSERT INTO square_checkouts(purchase_id,environment,merchant_id,location_id,idempotency_key,request_json,created_at) VALUES(?,?,?,?,?,?,?)',
                [$id,$this->square->value('environment'),$this->square->value('merchant_id'),$this->square->value('location_id'),$key,json_encode($body,JSON_THROW_ON_ERROR),Clock::nowSql()]);
            return $this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
        });
        $this->assertAccount($row);
        if (!in_array($row['status'],['creating','ready'],true)) throw new \RuntimeException('This checkout is closed. Refresh the package balance or contact the studio.');
        if (!$row['order_id']) {
            // Persist the exact request before contacting Square: timeout/crash retries reuse both payload and key.
            $link=$this->square->request('POST','/v2/online-checkout/payment-links',json_decode($row['request_json'],true,64,JSON_THROW_ON_ERROR))['payment_link'] ?? [];
            $url=$link['url'] ?? ''; $host=parse_url($url,PHP_URL_HOST);
            $allowed=$row['environment']==='sandbox' ? ['sandbox.square.link','sandbox.checkout.square.site'] : ['square.link','checkout.square.site'];
            if (empty($link['id']) || empty($link['order_id']) || parse_url($url,PHP_URL_SCHEME)!=='https' || !in_array($host,$allowed,true)
                || parse_url($url,PHP_URL_USER)!==null || parse_url($url,PHP_URL_PORT)!==null) throw new \RuntimeException('Square did not return a valid checkout. Please retry.');
            $this->db->run("UPDATE square_checkouts SET order_id=?,link_id=?,checkout_url=?,status='ready' WHERE purchase_id=? AND status='creating'",
                [$link['order_id'],$link['id'],$url,$id]);
        }
        // A retry can arrive after a previous payment. Read Square before returning a payable link.
        $this->reconcile($id);
        $row=$this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
        if ($row['status']!=='ready') return ['package'=>$this->packages->summary($token)];
        return ['checkout_url'=>$row['checkout_url'],'environment'=>$row['environment']];
    }

    private function assertAccount(array $row): void
    {
        if (!$this->square->configured() || $row['environment']!==$this->square->value('environment')
            || $row['merchant_id']!==$this->square->value('merchant_id') || $row['location_id']!==$this->square->value('location_id')) {
            throw new \RuntimeException('This order belongs to a different Square connection. Contact the studio.');
        }
    }

    public function reconcile(int $id): void
    {
        $row=$this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
        if (!$row || !$row['order_id']) return;
        $this->assertAccount($row);
        $order=$this->square->request('GET','/v2/orders/'.$row['order_id'])['order'] ?? [];
        if (($order['id'] ?? '')!==$row['order_id'] || ($order['location_id'] ?? '')!==$row['location_id']) throw new \RuntimeException('Square order verification failed.');
        $ids=[];
        foreach ($order['tenders'] ?? [] as $tender) if (!empty($tender['payment_id'])) $ids[]=$tender['payment_id'];
        if ($row['payment_id']) $ids[]=$row['payment_id'];
        foreach (array_unique($ids) as $paymentId) $this->processPayment((string)$paymentId);
        if (($order['state'] ?? '')==='CANCELED') $this->db->run("UPDATE square_checkouts SET status='closed' WHERE purchase_id=? AND status='ready'",[$id]);
        $this->db->run('UPDATE square_checkouts SET checked_at=? WHERE purchase_id=?',[Clock::nowSql(),$id]);
    }

    public function refresh(string $token): array
    {
        $id=$this->packages->authorizedPurchase($token); $this->reconcile($id);
        return $this->packages->summary($token);
    }

    public function webhook(string $body,string $signature): void
    {
        if (!$this->square->validSignature($body,$signature)) throw new \InvalidArgumentException('Invalid signature.');
        $event=json_decode($body,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($event) || !is_string($event['event_id'] ?? null) || strlen($event['event_id'])>128
            || ($event['merchant_id'] ?? '')!==$this->square->value('merchant_id')) throw new \InvalidArgumentException('Invalid event.');
        if ($this->db->value('SELECT 1 FROM square_events WHERE event_id=?',[$event['event_id']])) return;
        $type=$event['type'] ?? '';
        if (in_array($type,['payment.created','payment.updated'],true)) {
            $paymentId=$event['data']['object']['payment']['id'] ?? null;
            if (!is_string($paymentId) || $paymentId==='') throw new \InvalidArgumentException('Missing payment.');
            $this->processPayment($paymentId);
        } elseif (in_array($type,['refund.created','refund.updated'],true)) {
            $refundId=$event['data']['object']['refund']['id'] ?? null;
            if (!is_string($refundId) || $refundId==='') throw new \InvalidArgumentException('Missing refund.');
            $refund=$this->square->request('GET','/v2/refunds/'.$refundId)['refund'] ?? [];
            if (($refund['id'] ?? '')!==$refundId || empty($refund['payment_id'])) throw new \RuntimeException('Refund verification failed.');
            $this->processPayment($refund['payment_id']);
        }
        // Mark only after durable processing. Transient API/database errors remain retryable by Square.
        $this->db->transact(function() use($event,$type) {
            $this->db->run('INSERT OR IGNORE INTO square_events(event_id,event_type,processed_at) VALUES(?,?,?)',[$event['event_id'],$type,Clock::nowSql()]);
            $this->db->setSetting('square_webhook_verified',$this->square->fingerprint());
        });
    }

    private function processPayment(string $paymentId): void
    {
        $payment=$this->square->request('GET','/v2/payments/'.$paymentId)['payment'] ?? [];
        if (($payment['id'] ?? '')!==$paymentId) throw new \RuntimeException('Square payment verification failed.');
        $row=$this->db->one('SELECT * FROM square_checkouts WHERE order_id=?',[$payment['order_id'] ?? '']);
        if (!$row) {
            // A webhook can beat the checkout HTTP response. Force redelivery until the order mapping is saved.
            if ($this->db->value("SELECT 1 FROM square_checkouts WHERE status='creating' LIMIT 1")) throw new \RuntimeException('Checkout creation is still pending.');
            return; // Other Square Stand / account payments do not grant website package credits.
        }
        $this->assertAccount($row);
        if (($payment['location_id'] ?? '')!==$row['location_id']) throw new \RuntimeException('Square payment location does not match.');
        if (($payment['status'] ?? '')!=='COMPLETED') return;
        $this->db->transact(function() use($row,$payment,$paymentId) {
            $id=(int)$row['purchase_id'];
            $current=$this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
            $quote=(int)$this->db->value('SELECT quoted_price_cents FROM package_orders WHERE purchase_id=?',[$id]);
            if (($payment['amount_money']['currency'] ?? '')!=='USD' || ($payment['amount_money']['amount'] ?? null)!==$quote
                || ($payment['total_money']['currency'] ?? '')!=='USD' || ($payment['total_money']['amount'] ?? null)!==$quote) {
                $this->hold($id,'Payment amount or currency differs from the package price.'); return;
            }
            if ($current['payment_id'] && $current['payment_id']!==$paymentId) {
                $this->hold($id,'More than one completed payment was received. Review in Square.'); return;
            }
            $refund=(int)($payment['refunded_money']['amount'] ?? 0);
            if ($refund>0 || (int)$current['refunded_cents']>0) {
                $refund=max($refund,(int)$current['refunded_cents']);
                $state=$refund>=$quote?'refunded':'payment_review';
                $this->db->run('UPDATE square_checkouts SET payment_id=?,refunded_cents=?,status=?,review_reason=? WHERE purchase_id=?',
                    [$paymentId,$refund,$state,'Refund recorded in Square. Review any reserved appointments and remaining sessions.',$id]);
                $this->db->run('UPDATE package_purchases SET status=? WHERE id=?',[$state,$id]);
                return; // Keep used/reserved history; never issue a refund or silently alter appointments.
            }
            if ($current['status']==='payment_review' || $current['status']==='refunded') return;
            $p=$this->db->one('SELECT pp.status,o.payment_reference FROM package_purchases pp JOIN package_orders o ON o.purchase_id=pp.id WHERE pp.id=?',[$id]);
            $reference='square:'.$paymentId;
            if ($p['status']!=='pending_payment' && $p['payment_reference']!==$reference) {
                $this->hold($id,'Online payment arrived after the order was changed. Review in Square.'); return;
            }
            // Unique payment ID/reference and the package CAS make retries/concurrent events harmless.
            $this->packages->recordPayment($id,$quote,'Square',$reference,true);
            $this->db->run("UPDATE square_checkouts SET payment_id=?,status='paid',checked_at=? WHERE purchase_id=?",[$paymentId,Clock::nowSql(),$id]);
        });
    }

    private function hold(int $id,string $reason): void
    {
        $this->db->run("UPDATE square_checkouts SET status='payment_review',review_reason=? WHERE purchase_id=?",[$reason,$id]);
        $this->db->run("UPDATE package_purchases SET status='payment_review' WHERE id=?",[$id]);
    }

    public function closeCheckout(int $id): void
    {
        $row=$this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
        if (!$row || $row['status']==='closed') return;
        $this->assertAccount($row);
        if (!$row['link_id']) throw new \RuntimeException('Checkout creation is pending. Retry the customer checkout before closing it.');
        $this->reconcile($id);
        $row=$this->db->one('SELECT * FROM square_checkouts WHERE purchase_id=?',[$id]);
        if ($row['status']==='closed') return;
        if ($row['status']!=='ready') throw new \RuntimeException('This checkout already has a payment. Refresh and review it in Square.');
        $this->square->request('DELETE','/v2/online-checkout/payment-links/'.$row['link_id']);
        $this->reconcile($id); // Square cancels the associated order; independently confirm it cannot accept payment.
        if ($this->db->value('SELECT status FROM square_checkouts WHERE purchase_id=?',[$id])!=='closed') throw new \RuntimeException('Square has not confirmed cancellation. Do not collect another payment yet.');
    }
}
