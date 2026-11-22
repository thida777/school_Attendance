package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** Body of PUT /me/password. */
public class PasswordRequest {

    @SerializedName("current_password")
    private final String currentPassword;

    private final String password;

    @SerializedName("password_confirmation")
    private final String passwordConfirmation;

    public PasswordRequest(String currentPassword, String password, String passwordConfirmation) {
        this.currentPassword = currentPassword;
        this.password = password;
        this.passwordConfirmation = passwordConfirmation;
    }
}
