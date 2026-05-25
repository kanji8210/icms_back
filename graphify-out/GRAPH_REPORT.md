# Graph Report - icms_back  (2026-05-25)

## Corpus Check
- 1 files · ~2,527 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 40 nodes · 39 edges · 8 communities
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]

## God Nodes (most connected - your core abstractions)
1. `ICMS Plugin — Complete Technical & Functional Specification` - 12 edges
2. `Core Functionalities (Complete Feature Matrix)` - 6 edges
3. `Security Architecture` - 5 edges
4. `Plugin Lifecycle Management` - 5 edges
5. `Database Management System` - 4 edges
6. `Ethical & Legal Compliance Features` - 4 edges
7. `Integration Points` - 4 edges
8. `Development Standards` - 4 edges
9. `Table Architecture (5 Core Tables + 2 Log Tables)` - 2 edges
10. `Database Interaction Pattern` - 2 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Communities (8 total, 0 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.33
Nodes (6): Case Management Engine, Communication Tools, Core Functionalities (Complete Feature Matrix), EFNS Integration, Officer & User Management, Public Portal Capabilities

### Community 1 - "Community 1"
Cohesion: 0.33
Nodes (6): code:block1 (wp_icms_cases              — Primary case records (encrypted), code:block2 (Application Layer (UseCase)), Database Interaction Pattern, Database Management System, Database Management Tooling (Built into Plugin), Table Architecture (5 Core Tables + 2 Log Tables)

### Community 2 - "Community 2"
Cohesion: 0.33
Nodes (5): Core Objectives, Executive Overview, ICMS Plugin — Complete Technical & Functional Specification, Summary, Technology Stack (Plugin Internal)

### Community 3 - "Community 3"
Cohesion: 0.40
Nodes (5): Audit Trail Immutability, Authentication Flow, Data Protection Layers, Security Architecture, Security Features Excluded by Design

### Community 4 - "Community 4"
Cohesion: 0.40
Nodes (5): Deactivation, Installation, Plugin Lifecycle Management, Uninstallation, Updates

### Community 5 - "Community 5"
Cohesion: 0.50
Nodes (4): Code Architecture, Coding Standards, Development Standards, Testing Requirements

### Community 6 - "Community 6"
Cohesion: 0.50
Nodes (4): Anti-Corruption Design, Ethical & Legal Compliance Features, Kenya Data Protection Act (DPA 2019) Compliance, Right to Withdraw Clause (Built into Plugin Metadata)

### Community 7 - "Community 7"
Cohesion: 0.50
Nodes (4): Explicitly Blocked Integrations, Inbound Integrations, Integration Points, Outbound Integrations

## Knowledge Gaps
- **29 isolated node(s):** `Executive Overview`, `Core Objectives`, `code:block1 (wp_icms_cases              — Primary case records (encrypted)`, `Database Management Tooling (Built into Plugin)`, `code:block2 (Application Layer (UseCase))` (+24 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ICMS Plugin — Complete Technical & Functional Specification` connect `Community 2` to `Community 0`, `Community 1`, `Community 3`, `Community 4`, `Community 5`, `Community 6`, `Community 7`?**
  _High betweenness centrality (0.908) - this node is a cross-community bridge._
- **Why does `Core Functionalities (Complete Feature Matrix)` connect `Community 0` to `Community 2`?**
  _High betweenness centrality (0.243) - this node is a cross-community bridge._
- **Why does `Database Management System` connect `Community 1` to `Community 2`?**
  _High betweenness centrality (0.240) - this node is a cross-community bridge._
- **What connects `Executive Overview`, `Core Objectives`, `code:block1 (wp_icms_cases              — Primary case records (encrypted)` to the rest of the system?**
  _29 weakly-connected nodes found - possible documentation gaps or missing edges._