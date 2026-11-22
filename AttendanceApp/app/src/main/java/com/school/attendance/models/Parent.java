package com.school.attendance.models;

/** A student's parent: {"name": "...", "phone": "012345678", "relation": "father", "channel": "telegram"}. */
public class Parent {

    private String name;
    private String phone;
    private String relation;
    private String channel;

    public String getName() { return name; }
    public String getPhone() { return phone; }
    public String getChannel() { return channel; }

    /** "012345678" → "012 345 678". */
    public String getPhoneFormatted() {
        return phone == null ? "" : phone.replaceAll("(\\d{3})(?=\\d)", "$1 ");
    }
}
