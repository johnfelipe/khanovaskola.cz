<?php

namespace FrontModule;

use Nette\Application\UI\Form;


class RegistrationPresenter extends BaseFrontPresenter
{

	public function startup()
	{
		parent::startup();

		if ($this->user->loggedIn) {
			$this->flashMessage('Již jste zaregistrovaní, nepotřebujete se registrovat znovu.');
			$this->redirect(':Front:Homepage:');
		}
	}



	public function createComponentRegistrationForm($name)
	{
		$form = new Form($this, $name);

		$form->addText('mail', 'Mail');
		$form->addText('password', 'Heslo');
		$form->addText('code', 'Kód');

		$form->addSubmit('send');
		$form->onSuccess[] = callback($this, 'onSuccessRegistrationForm');

		return $form;
	}



	public function onSuccessRegistrationForm(Form $form)
	{
		$v = $form->values;

		if ($v->code !== "k12cz_mod") { // Gratuluji :) Můžete se podílet na vývoji Khanovy Školy <khan@dite.cz>
			$this->flashMessage('Špatný ověřovací kód.', 'error');
			$this['registrationForm']->addError(NULL);
			return FALSE;
		}

		$p = new \Password();
		$salt = $p->getRandomSalt();
		$hash = $p->calculateHash($v->password, $salt);

		$this->context->users->insert([
			'mail' => $v->mail,
			'password' => $hash,
			'salt' => $salt,
			'role' => 'moderator',
		]);

		$this->user->login($v->mail, $v->password);

		$this->redirect(':Moderator:Dashboard:');
	}

}
