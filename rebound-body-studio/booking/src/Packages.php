<?php
declare(strict_types=1);
namespace Rebound;

/** Package orders are separate from appointments. Credits activate only after recorded payment. */
final class Packages
{
    public function __construct(private Db $db, private Mailer $mail, private bool $onlinePayments = false) {}

    public function catalog(): array
    {
        return $this->db->all('SELECT p.id,p.name,p.sessions,p.price_cents,p.expires_days,
            MIN(s.duration_min) AS duration_min FROM packages p
            JOIN package_services ps ON ps.package_id=p.id JOIN services s ON s.id=ps.service_id
            WHERE p.active=1 AND s.active=1 GROUP BY p.id ORDER BY p.sort_order');
    }

    public function limit(string $key, int $maximum): void
    {
        $this->db->transact(function() use ($key,$maximum) {
            $now=Clock::nowSql(); $bucket=hash('sha256',$key);
            $this->db->run('DELETE FROM package_rate_limits WHERE expires_at < ?',[$now]);
            $this->db->run('INSERT INTO package_rate_limits(bucket,attempts,expires_at) VALUES(?,1,?)
                ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1',
                [$bucket,Clock::nowUtc()->modify('+15 minutes')->format(Clock::SQL)]);
            if ((int)$this->db->value('SELECT attempts FROM package_rate_limits WHERE bucket=?',[$bucket])>$maximum) {
                throw new \RuntimeException('Please wait 15 minutes before trying again.');
            }
        });
    }

    private function key(string $key): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new \RuntimeException('Please refresh the page and try again.');
        return hash('sha256',$key);
    }

    public function order(int $packageId, string $email, string $name, string $phone, bool $optOut, string $key): array
    {
        $hash=$this->key($key); $email=strtolower(trim($email)); $name=trim($name); $phone=trim($phone);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || !$name || strlen($name)>128 || strlen($phone)>64) {
            throw new \RuntimeException('Enter a valid name, email address and phone number.');
        }
        return $this->db->transact(function() use ($packageId,$email,$name,$phone,$optOut,$key,$hash) {
            $prior=$this->db->one('SELECT purchase_id FROM package_orders WHERE request_hash=?',[$hash]);
            if ($prior) return $this->summary($key);
            $pkg=null; foreach($this->catalog() as $item) if((int)$item['id']===$packageId) $pkg=$item;
            if (!$pkg) throw new \RuntimeException('This package is not currently available.');
            $client=$this->db->one('SELECT * FROM clients WHERE email=?',[$email]);
            if (!$client) {
                $id=$this->db->insert('INSERT INTO clients(email,name,phone,created_at,marketing_consent,consent_at,consent_source,unsub_token,unsubscribed_at)
                    VALUES(?,?,?,?,?,?,?,?,?)',[$email,$name,$phone,Clock::nowSql(),$optOut?0:1,$optOut?null:Clock::nowSql(),
                    $optOut?'package_opt_out':'package_default',Db::token(),$optOut?Clock::nowSql():null]);
            } else {
                $id=(int)$client['id'];
                // An unauthenticated order must not overwrite an existing customer's identity or opt-in.
                if($optOut) $this->db->run('UPDATE clients SET marketing_consent=0,unsubscribed_at=COALESCE(unsubscribed_at,?),consent_source=? WHERE id=?',
                    [Clock::nowSql(),'package_opt_out',$id]);
            }
            $purchase=$this->db->insert('INSERT INTO package_purchases(client_id,package_id,sessions_total,sessions_used,price_paid_cents,purchased_at,status)
                VALUES(?,?,?,0,0,?,?)',[$id,$packageId,$pkg['sessions'],Clock::nowSql(),'pending_payment']);
            $this->db->run('INSERT INTO package_orders(purchase_id,request_hash,quoted_price_cents,package_name,duration_min) VALUES(?,?,?,?,?)',
                [$purchase,$hash,$pkg['price_cents'],$pkg['name'],$pkg['duration_min']]);
            $this->db->run('INSERT INTO package_access(token_hash,purchase_id) VALUES(?,?)',[$hash,$purchase]);
            $paymentInstructions=$this->onlinePayments
                ? 'Complete secure Square checkout from your private package page below. Sessions activate after Square confirms payment. If you have already paid, refresh your balance.'
                : 'Call the studio at '.$this->db->setting('studio_phone','(480) 944-0494').' to arrange payment through Square or at the studio. Your sessions become available after the studio records payment.';
            $body="Hi {$name},\n\nYour package order #{$purchase}: {$pkg['name']} — ".$this->money((int)$pkg['price_cents']).
                ".\n\nNo appointment is required to order a package. This order was saved before payment. ".$paymentInstructions."\n\n".
                "View your order and session balance:\n".$this->url($key)."\n\nKeep this link private. Anyone you share it with can redeem sessions from this package.";
            $this->mail->queuePackage($email,$name,'Your package order — payment pending',$body,'package-order:'.$purchase);
            $studio=$this->db->setting('studio_email','');
            if($studio) $this->mail->queuePackage($studio,'Rebound Body Studio','Package order #'.$purchase.' — payment pending',
                "Package: {$pkg['name']}\nAmount: ".$this->money((int)$pkg['price_cents'])."\nCustomer: {$name}\nEmail: {$email}\nPhone: {$phone}\n\n".
                ($this->onlinePayments ? 'Order saved before checkout. Square payments activate sessions automatically. Check the dashboard for current payment status.' : 'No payment has been collected. Arrange payment with the customer, then record it in the Packages section of the studio dashboard.'),
                'package-order-studio:'.$purchase);
            return $this->summary($key);
        });
    }

    public function authorizedPurchase(string $token): int
    {
        $hash=$this->key($token);
        $id=$this->db->value('SELECT purchase_id FROM package_access WHERE token_hash=? AND (expires_at IS NULL OR expires_at>?)',[$hash,Clock::nowSql()]);
        if(!$id) throw new \RuntimeException('This package link is invalid or expired. Request a new link using your email.');
        return (int)$id;
    }

    public function summary(string $token): array
    {
        $id=$this->authorizedPurchase($token);
        $p=$this->row($id);
        $p['services']=$this->db->all('SELECT s.id,s.name,s.duration_min FROM services s JOIN package_services ps ON ps.service_id=s.id
            WHERE ps.package_id=? AND s.active=1 ORDER BY s.sort_order',[$p['package_id']]);
        $p['history']=$this->db->all('SELECT l.delta,l.reason,l.created_at,a.status,a.starts_at,s.name AS service_name
            FROM credit_ledger l LEFT JOIN appointments a ON a.id=l.appointment_id LEFT JOIN services s ON s.id=a.service_id
            WHERE l.package_purchase_id=? ORDER BY l.id DESC',[$id]);
        foreach($p['history'] as &$h) $h['when']=$h['starts_at']?Clock::human($h['starts_at']):null;
        // The balance link can be gifted; it does not reveal the purchaser's contact details.
        unset($p['client_email'],$p['client_name'],$p['client_phone'],$p['payment_reference']);
        $p['online_checkout_status']=null;
        if ($this->db->value("SELECT 1 FROM sqlite_master WHERE type='table' AND name='square_checkouts'")) {
            $p['online_checkout_status']=$this->db->value('SELECT status FROM square_checkouts WHERE purchase_id=?',[$id]);
        }
        return $p;
    }

    private function row(int $id): array
    {
        $p=$this->db->one('SELECT pp.id,pp.package_id,pp.sessions_total,pp.sessions_used,pp.status,pp.expires_at,pp.purchased_at,
            pp.price_paid_cents,o.quoted_price_cents,o.package_name,o.duration_min,o.paid_at,o.payment_method,o.payment_reference,
            c.name AS client_name,c.email AS client_email,c.phone AS client_phone
            FROM package_purchases pp JOIN package_orders o ON o.purchase_id=pp.id JOIN clients c ON c.id=pp.client_id WHERE pp.id=?',[$id]);
        if(!$p) throw new \RuntimeException('Package order not found.');
        if($p['status']==='active' && $p['expires_at'] && $p['expires_at']<=Clock::nowSql()) $p['status']='expired';
        $p['remaining']=(int)$p['sessions_total']-(int)$p['sessions_used'];
        $p['reserved']=(int)$this->db->value("SELECT count(*) FROM appointments WHERE package_purchase_id=? AND status IN ('requested','confirmed')",[$id]);
        $p['completed_or_used']=(int)$p['sessions_used']-$p['reserved'];
        return $p;
    }

    public function adminList(): array
    {
        $out=[]; foreach($this->db->all('SELECT purchase_id FROM package_orders ORDER BY purchase_id DESC') as $p) {
            $row=$this->row((int)$p['purchase_id']);
            $row['adjustments']=$this->db->all('SELECT delta,note,created_at FROM package_adjustments WHERE purchase_id=? ORDER BY created_at DESC',[$p['purchase_id']]);
            $out[]=$row;
        }
        return $out;
    }

    public function recordPayment(int $id, int $amount, string $method, string $reference, bool $verifiedSquare = false): void
    {
        $reference=trim($reference);
        if(!in_array($method,['Square','Cash','Other'],true) || strlen($reference)<3 || strlen($reference)>120) {
            throw new \RuntimeException('Choose a payment method and enter a receipt or payment reference.');
        }
        $this->db->transact(function() use($id,$amount,$method,$reference,$verifiedSquare) {
            if (!$verifiedSquare) $this->assertOfflineAllowed($id);
            $p=$this->row($id);
            if($p['paid_at'] && $p['payment_reference']===$reference && (int)$p['price_paid_cents']===$amount && $p['payment_method']===$method) return;
            if($p['status']!=='pending_payment') throw new \RuntimeException('This order is no longer awaiting payment.');
            if($amount!==(int)$p['quoted_price_cents']) throw new \RuntimeException('The payment must match the quoted package price.');
            if($this->db->value('SELECT 1 FROM package_orders WHERE payment_reference=?',[$reference])) throw new \RuntimeException('This receipt has already been recorded for another order.');
            $now=Clock::nowSql(); $expires=Clock::nowUtc()->modify('+1 year')->format(Clock::SQL);
            $n=$this->db->run("UPDATE package_purchases SET status='active',price_paid_cents=?,expires_at=? WHERE id=? AND status='pending_payment'",[$amount,$expires,$id])->rowCount();
            if($n!==1) throw new \RuntimeException('This order changed. Refresh and try again.');
            $this->db->run('UPDATE package_orders SET paid_at=?,payment_method=?,payment_reference=? WHERE purchase_id=?',[$now,$method,$reference,$id]);
            $this->mail->queuePackage($p['client_email'],$p['client_name'],'Your package is ready to use',
                "Your {$p['package_name']} package (#{$id}) is active.\nPayment recorded: ".$this->money($amount)." via {$method}.\n{$p['sessions_total']} sessions available. Redeem within one year of payment.\n\nUse the private link in your order email, or request a new link here:\n".$this->url().
                "\n\nChoose any available eligible session when you are ready. Randi reviews appointment requests before confirming them.",'package-paid:'.$id);
        });
    }

    public function voidOrder(int $id): void
    {
        $this->db->transact(function() use($id) {
            $this->assertOfflineAllowed($id);
            if($this->db->run("UPDATE package_purchases SET status='cancelled' WHERE id=? AND status='pending_payment'",[$id])->rowCount()!==1) {
                throw new \RuntimeException('Only unpaid orders can be cancelled here.');
            }
        });
    }

    private function assertOfflineAllowed(int $id): void
    {
        // Allows the existing regression/deployment baseline before migration 005 is installed.
        if (!$this->db->value("SELECT 1 FROM sqlite_master WHERE type='table' AND name='square_checkouts'")) return;
        $row=$this->db->one('SELECT status FROM square_checkouts WHERE purchase_id=?',[$id]);
        if ($row && $row['status']!=='closed') throw new \RuntimeException('Close the online checkout and confirm its cancellation before recording an offline payment or cancelling this order.');
    }

    public function adjust(int $id,int $delta,string $note,string $key): void
    {
        $hash=$this->key($key); $note=trim($note);
        if(!in_array($delta,[-1,1],true) || strlen($note)<5 || strlen($note)>500) throw new \RuntimeException('Add a reason for this one-session adjustment.');
        $this->db->transact(function() use($id,$delta,$note,$hash) {
            $prior=$this->db->one('SELECT * FROM package_adjustments WHERE request_hash=?',[$hash]);
            if($prior) {
                if((int)$prior['purchase_id']!==$id || (int)$prior['delta']!==$delta || $prior['note']!==$note) throw new \RuntimeException('Please refresh before making a new adjustment.');
                return;
            }
            $p=$this->row($id);
            if($p['status']!=='active') throw new \RuntimeException('Only active packages can be adjusted.');
            if($delta===-1 && $p['remaining']<1) throw new \RuntimeException('No sessions remain.');
            if($delta===1 && $p['completed_or_used']<1) throw new \RuntimeException('Only used sessions can be restored. Cancel a pending appointment to release its reserved session.');
            $this->db->run('UPDATE package_purchases SET sessions_used=sessions_used-? WHERE id=?',[$delta,$id]);
            $this->db->run('INSERT INTO credit_ledger(package_purchase_id,delta,reason,created_at) VALUES(?,?,?,?)',[$id,$delta,$delta===-1?'studio_session_used':'studio_session_restored',Clock::nowSql()]);
            $this->db->run('INSERT INTO package_adjustments(request_hash,purchase_id,delta,note,created_at) VALUES(?,?,?,?,?)',[$hash,$id,$delta,$note,Clock::nowSql()]);
        });
    }

    public function recover(string $email): void
    {
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Enter a valid email address.');
        $this->limit('recovery-email:'.$email,3);
        $this->db->transact(function() use ($email) {
            $rows=$this->db->all('SELECT pp.id,o.package_name,c.name FROM package_purchases pp JOIN package_orders o ON o.purchase_id=pp.id
                JOIN clients c ON c.id=pp.client_id WHERE c.email=? ORDER BY pp.id DESC',[$email]);
            if(!$rows) return;
            $body="Here are your package balance links. These replacement links are valid for 24 hours.\n\n";
            foreach($rows as $p) {
                $token=bin2hex(random_bytes(32));
                $this->db->run('INSERT INTO package_access(token_hash,purchase_id,expires_at) VALUES(?,?,?)',
                    [hash('sha256',$token),$p['id'],Clock::nowUtc()->modify('+24 hours')->format(Clock::SQL)]);
                $body.=$p['package_name'].' (#'.$p['id'].")\n".$this->url($token)."\n\n";
            }
            $body.='Keep these links private. Share a package link only with someone you want to use its sessions.';
            $this->mail->queuePackage($email,$rows[0]['name'],'Your package balance links',$body,'package-recovery:'.Db::token());
        });
    }

    private function url(string $token=''): string
    {
        return rtrim($this->db->setting('site_url',''),'/').'/booking/public/packages.html?v=20260912-square-3'.($token?'#token='.$token:'');
    }
    private function money(int $cents): string {return '$'.number_format($cents/100,2);}
}
