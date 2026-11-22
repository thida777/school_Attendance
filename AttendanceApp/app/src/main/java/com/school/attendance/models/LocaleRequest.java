package com.school.attendance.models;

/** Body of PUT /me: {"locale": "km"}. */
public class LocaleRequest {

    private final String locale;

    public LocaleRequest(String locale) {
        this.locale = locale;
    }
}
