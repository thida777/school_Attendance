package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** Body of POST /login: {"login": "012345678", "password": "...", "device_name": "Pixel 8"}. */
public class LoginRequest {

    private final String login;
    private final String password;

    @SerializedName("device_name")
    private final String deviceName;

    public LoginRequest(String login, String password, String deviceName) {
        this.login = login;
        this.password = password;
        this.deviceName = deviceName;
    }
}
