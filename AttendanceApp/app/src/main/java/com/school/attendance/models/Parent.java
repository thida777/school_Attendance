package com.school.attendance.models;

/** A student's parent: {"name": "...", "phone": "012345678", "relation": "father", "channel": "telegram"}. */
public class Parent {

    private String name;
    private String phone;
    private String relation;
    private String channel;

    /** Not on Telegram yet: the one-time invite link (t.me/...), shown as a QR code. Null when on Telegram. */
    @com.google.gson.annotations.SerializedName("invite_link")
    private String inviteLink;

    public String getName() { return name; }
    public String getPhone() { return phone; }
    public String getChannel() { return channel; }
    public String getInviteLink() { return inviteLink; }
    public String getRelation() { return relation; }
    public boolean onTelegram() { return "telegram".equals(channel); }

    /** "012345678" → "012 345 678". */
    public String getPhoneFormatted() {
        return phone == null ? "" : phone.replaceAll("(\\d{3})(?=\\d)", "$1 ");
    }
}
