<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateOperator extends Command
{
    protected $signature = 'assina:operator {email} {--name=Operador}';

    protected $description = 'Cria uma conta de operador com senha solicitada de forma oculta.';

    public function handle(): int
    {
        $password = $this->secret('Senha (mínimo 12 caracteres)');
        $data = ['email' => mb_strtolower($this->argument('email')), 'name' => $this->option('name'), 'password' => $password];
        $validator = Validator::make($data, ['email' => ['required', 'email', 'unique:users'], 'name' => ['required', 'max:160'], 'password' => ['required', 'min:12']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = User::create($data);
        $user->forceFill(['email_verified_at' => now()]);
        $user->save();
        $this->info('Operador criado. O provisionador confirmou a titularidade do e-mail.');

        return self::SUCCESS;
    }
}
