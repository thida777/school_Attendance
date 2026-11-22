package com.school.attendance.models;

/** Answer of POST /login: {"token": "1|abc...", "user": {...}}. */
public class LoginResponse {

    private String token;
    private User user;

    public String getToken() { return token; }
    public User getUser() { return user; }
}
