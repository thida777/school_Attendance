package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** The logged-in teacher, as the API sends it (UserResource in Laravel). */
public class User {

    private int id;
    private String name;

    @SerializedName("name_km")
    private String nameKm;

    private String email;
    private String phone;

    @SerializedName("photo_url")
    private String photoUrl;

    private String initials;
    private String role;
    private String locale;

    @SerializedName("homeroom_class")
    private ClassRef homeroomClass;

    public int getId() { return id; }
    public String getName() { return name; }
    public String getNameKm() { return nameKm; }
    public String getEmail() { return email; }
    public String getPhone() { return phone; }
    public String getPhotoUrl() { return photoUrl; }
    public String getRole() { return role; }
    public String getLocale() { return locale; }
    public ClassRef getHomeroomClass() { return homeroomClass; }

    /** The name in the app's language (Khmer name when the app is in Khmer). */
    public String displayName(boolean khmer) {
        return khmer && nameKm != null && !nameKm.isEmpty() ? nameKm : name;
    }

    /** The other name, shown small under the main one. */
    public String otherName(boolean khmer) {
        return khmer ? name : nameKm;
    }

    /** "PS" for Pen Sovann (the API sends it; this is a fallback). */
    public String getInitials() {
        if (initials != null && !initials.isEmpty()) return initials;
        StringBuilder letters = new StringBuilder();
        for (String word : name.trim().split("\\s+")) {
            if (!word.isEmpty() && letters.length() < 2) letters.append(Character.toUpperCase(word.charAt(0)));
        }
        return letters.toString();
    }

    /** "012345678" → "012 345 678", like on the website. */
    public String getPhoneFormatted() {
        if (phone == null) return "";
        return phone.replaceAll("(\\d{3})(?=\\d)", "$1 ");
    }
}
