package com.school.attendance.models;

/** Answer of GET /me: {"data": {...user...}}. */
public class UserResponse {

    private User data;

    public User getData() { return data; }
}
