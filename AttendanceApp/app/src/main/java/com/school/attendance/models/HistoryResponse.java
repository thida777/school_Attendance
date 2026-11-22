package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

import java.util.ArrayList;
import java.util.List;

/** GET /history: {"data": [...], "meta": {"current_page": 1, "last_page": 3, "total": 70}}. 30 per page. */
public class HistoryResponse {

    private List<HistoryRecord> data;
    private Meta meta;

    public List<HistoryRecord> getData() {
        return data == null ? new ArrayList<>() : data;
    }

    public int getCurrentPage() { return meta == null ? 1 : meta.currentPage; }
    public int getLastPage() { return meta == null ? 1 : meta.lastPage; }
    public int getTotal() { return meta == null ? getData().size() : meta.total; }

    static class Meta {
        @SerializedName("current_page")
        int currentPage;

        @SerializedName("last_page")
        int lastPage;

        int total;
    }
}
