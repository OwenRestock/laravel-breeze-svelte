<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class LoanBill extends Authenticatable
{
    protected $table = 'loan_bills';
    protected $guarded = ['id'];
    protected $dates = [
        'date_billing',
        'settled_at',
    ];

    protected $casts = [
    ];

    public static function outstanding($date)
    {
        $date = $date ?: now()->format('Y-m-d');

        return self::whereHas('loan',
            function ($query) use ($date) {
                $query->whereIn('status', ['Active', 'Completed'])
                    ->whereDate('disbursement_date', '<=', $date);
            })
            ->where(function ($where) use ($date) {
                $where->whereNull('settled_at')
                    ->orWhereDate('settled_at', '>', $date);
            });
    }

    public function loan()
    {
        return $this->belongsTo('App\Models\Loan');
    }

    public function proof()
    {
        return $this->hasMany('App\Models\DocumentLoan')->where('type', 'proof');
    }

    // public function document()
    // {
    //     return $this->hasOne(DocumentLoan::class)->where('type', 'proof');
    // }

    // public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    // {
    //     return $this->hasMany(LoanBillPayment::class);
    // }

    // public function transfers(): \Illuminate\Database\Eloquent\Relations\HasMany
    // {
    //     return $this->hasMany(LoanBillTransfer::class);
    // }

    // public function lateFees(): HasMany
    // {
    //     return $this->hasMany(LoanBillLateFee::class);
    // }

    // public function taxes(): \Illuminate\Database\Eloquent\Relations\MorphOne
    // {
    //     return $this->morphOne(Tax::class, 'taxable');
    // }

    // /* Mutator */

    // public function getAmountRupiahAttribute()
    // {
    //     return Helper::rupiah((int)$this->amount);
    // }

    // Append: amount_late_fee
    public function getAmountLateFeeAttribute(): int
    {
        return (int) $this->amount + (int) $this->late_fee;
    }

    /* Condition */

    public function scopeApproved($query)
    {
        return $query->whereStatus('Approved');
    }

    public function scopePending($query)
    {
        return $query->whereStatus('Pending');
    }

    public function scopePendingOrApproved($query)
    {
        return $query->whereIn('status', ['Pending', 'Approved']);
    }

    public function scopeWaiting($query)
    {
        return $query->whereStatus('Waiting');
    }

    public function scopePendingOrWaiting($query)
    {
        return $query->whereIn('status', ['Pending', 'Waiting']);
    }

    public function scopeNotApproved($query)
    {
        return $query->where('status', '!=', 'Approved');
    }

    /* Get */

    public function getProof()
    {
        return $this->proof()->whereType('proof')->first();
    }

    public function getLastDifference()
    {
        $last = self::whereDate('date_billing', $this->date_billing->subMonth()->format('Y-m-d'))
            ->where('loan_id', $this->loan_id)->first();

        return $last->difference ?? 0;
    }

    public function getLateFeeInDay()
    {
        $late = $this->dpdLast($this) - config('constant.late_fee.day');

        return $late > 0 ? $late : 0;
    }

    // /**
    //  * @param bool $isThisMonth false for until this month
    //  * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
    //  */
    // public static function collection($isThisMonth = true, $date = null, $dpd = null)
    // {
    //     $date = $date ?: now();
    //     $funds = Fund::select(['funds.loan_id', 'fund_bills.date_billing', DB::raw('count(*) as pending')])
    //         ->join('fund_bills', 'fund_bills.fund_id', '=', 'funds.id')
    //         ->where(
    //             function ($bill) use ($date) {
    //                 $bill->where(
    //                     function ($bill) use ($date) {
    //                         $bill->whereDate('fund_bills.date_billing', '>', $date)
    //                             ->whereDate('fund_bills.settled_at', '>', $date);
    //                     })->orWhereDate('fund_bills.settled_at', '>', $date)
    //                     ->orWhereNull('fund_bills.settled_at');
    //             })
    //         ->where('fund_bills.amount', '>', 0)
    //         ->whereIn('funds.status', ['Active', 'Completed'])
    //         ->groupBy('funds.loan_id')
    //         ->groupBy('fund_bills.date_billing');

    //     $query = self::select([
    //         'loan_bills.*', 'loans.loan_nominal', 'loans.unique_code', 'loans.tenor', 'users.referral_code',
    //         'persons.name as person_name', 'companies.name as company_name', 'companies.business', 'users.type',
    //         'repayment.pending', DB::raw("(CASE WHEN users.type = 'INDIVIDU' then persons.name ELSE CONCAT(companies.name, ', ', companies.business) END) as name")
    //     ])
    //         ->join('loans', 'loans.id', '=', 'loan_bills.loan_id')
    //         ->join('users', 'users.id', '=', 'loans.user_id')
    //         ->leftJoin('persons', function ($join) {
    //             $join->on('persons.user_id', '=', 'users.id')
    //                 ->where('persons.type', 'PIC');
    //         })
    //         ->leftJoin('companies', function ($join) {
    //             $join->on('companies.user_id', '=', 'users.id')
    //                 ->where('companies.type', '=', 'company');
    //         })
    //         ->leftJoinSub($funds, 'repayment', function ($join) {
    //             $join->on('repayment.loan_id', '=', 'loan_bills.loan_id')
    //                 ->whereColumn('repayment.date_billing', 'loan_bills.date_billing');
    //         })
    //         ->whereIn('loans.status', ['Active', 'Completed'])
    //         ->where(
    //             function ($bill) use ($date) {
    //                 $bill->where(
    //                     function ($bill) use ($date) {
    //                         $bill->whereDate('loan_bills.date_billing', '>', $date)
    //                             ->whereDate('loan_bills.settled_at', '>', $date);
    //                     })->orWhereDate('loan_bills.settled_at', '>', $date)
    //                     ->orWhereNull('loan_bills.settled_at')
    //                     ->orWhere('repayment.pending', '>', 0);
    //             })
    //         ->where('amount', '>', 0)
    //         ->where('loan_bills.amount', '!=', 0);

    //     if ($isThisMonth) {
    //         $query->whereBetween('loan_bills.date_billing', [
    //             $date->copy()->firstOfMonth()->format('Y-m-d'),
    //             $date->copy()->endOfMonth()->format('Y-m-d')
    //         ]);
    //     } else {
    //         $query->whereDate('loan_bills.date_billing', $dpd == 1 ? '>=' : '<', $date);
    //     }

    //     return $query;
    // }

    /*
     * Menghitung biaya admin lainnya
     * Biaya Admin = Asuransi+Provisi
     * */
    public function getBiayaAdminAttribute()
    {
        return $this->insurance + $this->provision;
    }

    /*
     * hitung dpd dari bill yang blm dibayar
     * */
    public function dpd()
    {
        return $this->date_billing->diffInDays(Carbon::now());
    }

    public function sumPayments($only = null)
    {
        $paid = 0;
        $payments = $this->payments();

        $arrays = $only ? [$only] : config('constant.loan_bills_transaction_field');
        foreach ($arrays as $field) {
            $paid += $payments->sum($field);
        }

        return $paid;
    }

    public function getLastMainPaid()
    {
        $payment = $this->payments->filter(function ($payment) {
            return $payment->main > 0;
        })->sortByDesc(function ($payment) {
            return $payment->transfer->transferred_at;
        })->first();

        if ($payment) {
            return $payment->transfer->transferred_at;
        }

        return null;
    }

    public function sumLateFeeAfterMainPaid(Carbon $date)
    {
        return $this->payments->filter(function ($payment) use ($date) {
            return $payment->transfer->transferred_at > $date;
        })->sum('late_fee');
    }

    public function sumAmountWithoutLateFee()
    {
        $amount = 0;
        foreach (config('constant.loan_bills_transaction_field') as $field) {
            if ($field === 'late_fee') {
                continue;
            }
            $amount += $this->{$field};
        }

        return $amount;
    }

    public function outstandingWithoutLateFee()
    {
        $amount = 0;

        foreach (config('constant.loan_bills_transaction_field') as $field) {
            if ($field === 'late_fee') {
                continue;
            }

            $amount += $this->{'outstanding_' . $field};
        }

        return (int) $amount;
    }
}
