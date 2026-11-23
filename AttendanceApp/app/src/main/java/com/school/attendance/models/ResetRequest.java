package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** Body of POST /password/reset: the account, the 6-digit code from Telegram and the new password. */
public class ResetRequest {

    private final String login;
    private final String code;
    private final String password;

    @SerializedName("password_confirmation")
    private final String passwordConfirmation;

    public ResetRequest(String login, String code, String password, String passwordConfirmation) {
        this.login = login;
        this.code = code;
        this.password = password;
        this.passwordConfirmation = passwordConfirmation;
    }
}
