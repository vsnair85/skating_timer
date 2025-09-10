<?php
error_reporting(E_ALL);

class skatingApi
{
	var $db;

	function __construct()
	{
		$this->db = mysqli_connect("localhost", "root", "", "skating_timer");
	}

	function loginUser($phone, $password)
	{
		// Use a prepared statement to prevent SQL injection
		$sql = "SELECT * FROM `tbl_users` WHERE `tu_mobile`= ?";
		$stmt = mysqli_prepare($this->db, $sql);
		mysqli_stmt_bind_param($stmt, "s", $phone);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (mysqli_num_rows($result) > 0) {
			$row = mysqli_fetch_assoc($result);

			if ($row) {
				// Check if user profile is activated
				if ($row['tu_status'] != '1') {
					return ["error_code" => 1212, "message" => "The Account is not active now. Please Contact Admin."];
				}

				// Verify password
				if (password_verify($password, $row['tu_pass'])) {
					return (object) $row;
				}
			}
		}

		return false;
	}

	function listRacers()
	{
		// Use a prepared statement to prevent SQL injection
		$sql = "SELECT tr_name FROM `tbl_racers`";
		$stmt = mysqli_prepare($this->db, $sql);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (mysqli_num_rows($result) > 0) {
			$racers = [];
			while ($row = mysqli_fetch_assoc($result)) {
				$racers[] = (object) $row;
			}
			return $racers;
		}

		return false;
	}
}
