package com.school.attendance.models;

/** Body of POST /password/forgot: the phone number (or email) of the account. */
public class ForgotRequest {

    private final String login;

    public ForgotRequest(String login) {
        this.login = login;
    }
}
