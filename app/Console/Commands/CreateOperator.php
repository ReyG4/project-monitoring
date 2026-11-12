<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateOperator extends Command
{
    /**
     * Execute the console command.
     */

    protected $signature = 'app:create-operator';
    protected $description = 'Buat akun operator pertama';

    public function handle()
    {
        $name = $this->ask('Nama');
        $email = $this->ask('Email');
        $password = $this->secret('Password (Minimal 8 karakter, huruf besar-kecil, dan angka)');
        $confirmation = $this->secret('Ulangin password');

        if ($password !== $confirmation){
            $this->error('Password dan pengulangannya tidak sama');
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:225'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()){
            foreach($validator->errors()->all() as $error){
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new user(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = Role::Operator;
        $user->save();

        $this->info('Operator Berhasil Dibuat.');
        return self::SUCCESS;
    }
}