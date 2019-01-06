<?php

class Service
{
	/**
	 * Main function
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response $response)
	{
		$response->setCache("year");
		$response->setTemplate("home.ejs", []);
	}

	/**
	 * Apply a coupon
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _canjear(Request $request, Response $response)
	{
		// get coupon from the database
		$couponCode = strtoupper($request->input->data->coupon);
		$coupon = Connection::query("SELECT * FROM _cupones WHERE coupon = '$couponCode' AND active=1");

		// check if coupon cannot be found
		if(empty($coupon)) {
			return $response->setTemplate("message.ejs", [
				"header"=>"El cupon no existe",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "El cupon insertado ($couponCode) no existe o se encuentra desactivado. Por favor revise los caracteres insertados e intente nuevamente."
			]);
		}

		// check if the coupon has been used already by the user
		$used = Connection::query("SELECT COUNT(id) AS used FROM _cupones_used WHERE email='{$request->person->email}' AND coupon='$couponCode'")[0]->used;
		if($used) {
			return $response->setTemplate("message.ejs", [
				"header"=>"El cupon ya fue usado",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "Lo sentimos, pero el cupon insertado ($couponCode) ya fue usado por usted, y solo puede aplicarse una vez por usuario."
			]);
		}

		// check if the coupon reached the usage limit
		$coupon = $coupon[0];
		if($coupon->rule_limit) {
			$cnt = Connection::query("SELECT COUNT(id) AS cnt FROM _cupones_used WHERE coupon='$couponCode'")[0]->cnt;
			if($coupon->rule_limit <= $cnt) {
				return $response->setTemplate("message.ejs", [
					"header"=>"El cupon alcanzo su maximo",
					"icon"=>"sentiment_very_dissatisfied",
					"text" => "Este cupon ($couponCode) ha sido usado demasidas veces y ahora se encuentra desactivado."
				]);
			}
		}

		// check if the new user rule can be applied
		if($coupon->rule_new_user) {
			$newUser = Connection::query("SELECT COUNT(email) AS newuser FROM person WHERE email = '{$request->person->email}' AND DATEDIFF(NOW(), insertion_date) < 3")[0]->newuser;
			if( ! $newUser) {
				return $response->setTemplate("message.ejs", [
					"header"=>"El cupon no aplica",
					"icon"=>"sentiment_very_dissatisfied",
					"text" => "Lo sentimos, pero el cupon insertado ($couponCode) solo puede aplicarse a nuevos usuarios."
				]);
			}
		}

		// check if the deadline rule can be applied
		if($coupon->rule_deadline) {
			if(date('Y-m-d') > date('Y-m-d', strtotime($coupon->rule_deadline))) {
				return $response->setTemplate("message.ejs", [
					"header"=>"El cupon ha expirado",
					"icon"=>"sentiment_very_dissatisfied",
					"text" => "Lo sentimos, pero el cupon insertado ($couponCode) ha expirado y no puede ser usado."
				]);
			}
		}

		// add credits to the user
		$credits = $coupon->prize_credits;
		Connection::query("UPDATE person SET credit=credit+$credits WHERE email='{$request->person->email}'");

		// create records of your interaction
		Connection::query("INSERT INTO _cupones_used(coupon, email) VALUES ('$couponCode', '{$request->person->email}')");

		// offer rewards response
		$response->setTemplate("message.ejs", [
			"header"=>"&iexcl;Felicidades!",
			"icon"=>"sentiment_very_satisfied",
			"text" => "Su cupon se ha canjeado correctamente y usted ha ganado <b>&sect;$credits en creditos de Apretaste</b>. Gracias por canjear su cupon."
		]);
	}
}